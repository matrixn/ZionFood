<?php

namespace App\Domain\Imports;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GloriaFoodClient
{
    public function fetch(#[\SensitiveParameter] string $key): array
    {
        $response = Http::withHeaders(['Authorization' => $key, 'Glf-Api-Version' => '2'])->acceptJson()->connectTimeout(5)->timeout(30)->withoutRedirecting()->withOptions(['proxy' => '', 'verify' => true, 'on_headers' => function ($r) {
            if ((int) $r->getHeaderLine('Content-Length') > 5242880) {
                throw new RuntimeException('import_response_limit');
            }
        }, 'progress' => function ($total, $received) {
            if ($received > 5242880) {
                throw new RuntimeException('import_response_limit');
            }
        }])->get('https://pos.globalfoodsoft.com/pos/menu');
        if (! $response->successful() || strlen($response->body()) > 5242880) {
            throw new RuntimeException('import_fetch_failed');
        }
        $data = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('import_payload_invalid');
        }

        return $data;
    }
}
