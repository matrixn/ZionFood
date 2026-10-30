<?php

namespace App\Http\Middleware;

use App\Models\WordpressConnection;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class WordpressIdempotency
{
    public function handle(Request $request, Closure $next)
    {
        $key = (string) $request->header('Idempotency-Key');
        validator(['key' => $key], ['key' => 'required|string|min:16|max:100|regex:/^[A-Za-z0-9_-]+$/'])->validate();
        abort_if(strlen($request->getContent()) > 5242880 || (int) $request->header('Content-Length') > 5242880, 413);
        $canonical = function ($value) use (&$canonical) {
            if ($value instanceof UploadedFile) {
                return ['sha256' => hash_file('sha256', $value->getRealPath()), 'mime' => $value->getMimeType()];
            }
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($canonical($request->all()), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $next, $key, $hash) {
            $context = $request->attributes->get('wordpress_connection');
            $connection = WordpressConnection::whereKey($context->id)->lockForUpdate()->firstOrFail();
            $token = PersonalAccessToken::find($request->user()->currentAccessToken()->id);
            abort_unless($connection->active && $token && (! $token->expires_at || $token->expires_at->isFuture()), 401);
            $record = DB::table('wordpress_requests')->where('wordpress_connection_id', $connection->id)->where('key_hash', hash('sha256', $key))->first();
            if ($record) {
                abort_unless(hash_equals($record->request_hash, $hash), 409, __('Idempotency key was used with different checkout data.'));
                abort_unless($record->response && now()->lt($record->expires_at), 409, 'idempotency_response_expired');
                $body = json_decode(Crypt::decryptString($record->response), true, 64, JSON_THROW_ON_ERROR);
                $status = $record->http_status;
                if (array_key_exists('replayed', $body)) {
                    $body['replayed'] = true;
                    if ($status === 201) {
                        $status = 200;
                    }
                }

                return response()->json($body, $status)->header('Idempotency-Replayed', 'true');
            }
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                throw new HttpResponseException($response);
            }
            if ($response->getStatusCode() < 400) {
                $body = $response->getContent() ?: '{}';
                if (strlen($body) > 1048576) {
                    throw new \RuntimeException('Integration mutation response exceeds the supported limit.');
                }
                DB::table('wordpress_requests')->insert(['wordpress_connection_id' => $connection->id, 'key_hash' => hash('sha256', $key), 'request_hash' => $hash, 'http_status' => $response->getStatusCode(), 'response' => Crypt::encryptString($body), 'expires_at' => now()->addDays(config('wordpress.response_retention_days')), 'created_at' => now(), 'updated_at' => now()]);
            }

            return $response;
        }, 3);
    }
}
