import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Jika diakses via ngrok/tunnel, wsHost harus ikut hostname browser
// bukan hardcoded 127.0.0.1 yang tidak bisa dijangkau dari luar
const reverbHost = import.meta.env.VITE_REVERB_HOST;
const isLocalhost = reverbHost === '127.0.0.1' || reverbHost === 'localhost';
const wsHost = isLocalhost ? window.location.hostname : reverbHost;

// Port: kalau ngrok (port 80/443), tidak perlu specify port
const isDefaultPort = window.location.port === '' || window.location.port === '80' || window.location.port === '443';
const wsPort  = isLocalhost ? (import.meta.env.VITE_REVERB_PORT ?? 8080) : (isDefaultPort ? undefined : window.location.port);
const wssPort = wsPort;
const useTLS  = (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https' || window.location.protocol === 'https:';

window.Echo = new Echo({
    broadcaster:       'reverb',
    key:               import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:            wsHost,
    wsPort:            wsPort ?? 80,
    wssPort:           wssPort ?? 443,
    forceTLS:          useTLS,
    enabledTransports: ['ws', 'wss'],
});