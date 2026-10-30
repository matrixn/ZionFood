<?php

namespace App\Http\Middleware;

use App\Domain\Integrations\CommunicationJournal;
use App\Models\WordpressConnection;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class LogWordpressCommunication
{
    public function handle(Request $request, Closure $next)
    {
        $connection = null;
        try {
            $bearer = $request->bearerToken();
            $token = $bearer && strlen($bearer) < 256 && preg_match('/^[0-9]+[|][A-Za-z0-9]+$/D', $bearer) ? PersonalAccessToken::findToken($bearer) : null;
            $connection = $token?->wordpress_connection_id ? WordpressConnection::find($token->wordpress_connection_id) : null;
        } catch (\Throwable) {
        }
        if (! $connection && ! $request->is('api/v1/wordpress', 'api/v1/wordpress/*')) {
            return $next($request);
        }
        $request->headers->set('Accept', 'application/json');
        $id = (string) Str::uuid();
        $started = hrtime(true);
        $request->attributes->set('communication_request_id', $id);
        try {
            $response = $next($request);
        } catch (\Throwable $error) {
            $handler = app(ExceptionHandler::class);
            $handler->report($error);
            $response = $handler->render($request, $error);
        }
        $status = $response->getStatusCode();
        $location = $request->attributes->get('wordpress_location');
        if ($location && (! $connection || (int) $location->restaurant_id !== (int) $connection->restaurant_id)) {
            $location = null;
        }
        app(CommunicationJournal::class)->write(['request_id' => $id, 'organization_id' => $connection?->organization_id, 'restaurant_id' => $connection?->restaurant_id, 'location_id' => $location?->id, 'wordpress_connection_id' => $connection?->id, 'direction' => 'inbound', 'operation' => substr($request->route()?->uri() ?? 'wordpress.unknown', 0, 150), 'method' => $request->method(), 'http_status' => $status, 'outcome' => $status < 400 ? 'success' : ($status < 500 ? 'client_error' : 'server_error'), 'duration_ms' => (int) min(4294967295, (hrtime(true) - $started) / 1000000), 'replayed' => $response->headers->get('Idempotency-Replayed') === 'true']);
        $response->headers->set('X-ZionFood-Request-ID', $id);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
