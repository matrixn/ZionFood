import { csrfHeaders } from '@/lib/http';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
let echo: Echo<'reverb'> | null = null;
export function getEcho(): Echo<'reverb'> | null {
    if (!import.meta.env.VITE_REVERB_APP_KEY) return null;
    if (!echo)
        echo = new Echo({
            broadcaster: 'reverb',
            client: new Pusher(import.meta.env.VITE_REVERB_APP_KEY, {
                wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
                wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
                wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
                forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
                enabledTransports: ['ws', 'wss'],
                cluster: '',
                channelAuthorization: {
                    endpoint: '/broadcasting/auth',
                    transport: 'ajax',
                    headersProvider: csrfHeaders,
                },
            }),
        });
    return echo;
}
