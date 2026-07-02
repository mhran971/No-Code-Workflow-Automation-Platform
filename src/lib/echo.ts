import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Reverb uses the Pusher protocol under the hood.
// The window.Pusher assignment is required by laravel-echo's Pusher connector.
(window as unknown as Record<string, unknown>).Pusher = Pusher;

export function createEcho(token: string): Echo<'pusher'> {
    return new Echo({
        broadcaster: 'pusher',
        key: 'fa314367b08646e41d11',               // PUSHER_APP_KEY
        cluster: 'eu',                        // PUSHER_APP_CLUSTER
        forceTLS: true,                        // always true with Pusher
        authEndpoint: 'https://workflow-api.hudashakir.serv00.net/api/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        },
    });
}
