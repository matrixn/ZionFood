<?php

namespace App\Domain\Imports;

use App\Domain\Integrations\PublicWebhookDestination;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\CurlMultiHandler;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GloriaFoodPublicClient
{
    public const API = 'https://www.foodbooking.com/api/cart/init';

    public const CDN = 'https://www.fbgcdn.com/pictures/';

    public function uid(string $url): string
    {
        if (! preg_match('~^https://(?:www\.)?foodbooking\.com/ordering(?:/restaurant/menu|/?)\?restaurant_uid=([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$~iD', $url, $matches)) {
            throw ValidationException::withMessages(['public_menu_url' => __('Use the public FoodBooking menu link containing restaurant_uid.')]);
        }

        return strtolower($matches[1]);
    }

    public function options(string $url, int $limit): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! in_array($host, ['www.foodbooking.com', 'www.fbgcdn.com'], true)) {
            throw new \RuntimeException('public_menu_destination');
        }
        $target = app(PublicWebhookDestination::class)->resolve($url);
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];

        return ['allow_redirects' => false, 'proxy' => '', 'verify' => true, 'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ip]], 'on_headers' => function ($response) use ($limit) {
            if ((int) $response->getHeaderLine('Content-Length') > $limit) {
                throw new \RuntimeException('public_menu_response_limit');
            }
        }, 'progress' => function ($total, $received) use ($limit) {
            if ($received > $limit) {
                throw new \RuntimeException('public_menu_response_limit');
            }
        }];
    }

    public function menu(string $url): array
    {
        $uid = $this->uid($url);
        $response = Http::connectTimeout(3)->timeout(7)->setHandler(new CurlHandler)->withOptions($this->options(self::API, 5242880))
            ->acceptJson()->post(self::API, ['restaurant_uid' => $uid, 'payload' => ['language_code' => 'ro', 'init' => 1, 'source' => 'website', 'reference' => null]]);
        if (! $response->successful() || strlen($response->body()) > 5242880 || ! str_contains(strtolower((string) $response->header('Content-Type')), 'application/json')) {
            throw new \RuntimeException('public_menu_fetch');
        }
        $data = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($data['restaurant'] ?? null) || ($data['restaurant']['uid'] ?? null) !== $uid) {
            throw new \RuntimeException('public_menu_identity');
        }

        return $data['restaurant'];
    }

    public function photos(array $urls): array
    {
        $handler = new CurlMultiHandler;

        return Http::pool(function (Pool $pool) use ($urls, $handler) {
            foreach ($urls as $key => $url) {
                if (! preg_match('~^https://www\.fbgcdn\.com/pictures/[a-f0-9-]{36}(?:_d[23])?\.(?:jpg|jpeg|png|webp)$~D', $url)) {
                    throw new \RuntimeException('public_photo_destination');
                }
                $pool->as((string) $key)->connectTimeout(3)->timeout(8)->setHandler($handler)->withOptions($this->options($url, 3145728))->get($url);
            }
        });
    }

    public function imageBytes(mixed $response): string
    {
        if (! $response instanceof Response || ! $response->successful() || strlen($response->body()) > 3145728 || ! in_array(strtolower(explode(';', (string) $response->header('Content-Type'))[0]), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('public_photo_response');
        }

        return $response->body();
    }
}
