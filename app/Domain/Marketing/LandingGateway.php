<?php

namespace App\Domain\Marketing;

use Inertia\Ssr\Gateway;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;

class LandingGateway implements Gateway
{
    public function dispatch(array $page): ?Response
    {
        if (($page['component'] ?? null) !== 'Welcome') {
            return app(HttpGateway::class)->dispatch($page);
        }
        $markup = app(LandingPrerender::class)->markup($page['props']['locale'] ?? 'ro', ! empty($page['props']['auth']['user']));
        if ($markup === null) {
            return null;
        }
        // The rendered content is build-owned; session/user data only comes from this request.
        $json = json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        return new Response('', '<script data-page="app" type="application/json">'.$json.'</script><div id="app">'.$markup.'</div>');
    }
}
