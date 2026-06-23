window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: 443, // Ngrok HTTPS menggunakan port 443
    wssPort: 443,
    forceTLS: true, // Sekarang kita pakai HTTPS, jadi true
    enabledTransports: ['ws', 'wss'],
});