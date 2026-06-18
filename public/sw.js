// public/sw.js
// Service Worker — GPS Background Tracking

const SW_VERSION  = 'v1';
const GPS_INTERVAL = 3000; // kirim GPS tiap 3 detik

let gpsWatchId   = null;
let sendInterval = null;
let currentPos   = null;
let config       = {
    serverUrl: '',
    deviceId:  '',
    active:    false,
};

// ── Terima pesan dari halaman ─────────────────────────────────────
self.addEventListener('message', (event) => {
    const { type, data } = event.data;

    switch (type) {
        case 'START_GPS':
            config = { ...config, ...data, active: true };
            startTracking();
            break;

        case 'STOP_GPS':
            config.active = false;
            stopTracking();
            break;

        case 'GET_STATUS':
            event.source.postMessage({
                type:    'STATUS',
                active:  config.active,
                pos:     currentPos ? {
                    lat:   currentPos.latitude,
                    lng:   currentPos.longitude,
                    speed: currentPos.speed,
                } : null,
            });
            break;
    }
});

// ── Start GPS tracking ────────────────────────────────────────────
function startTracking() {
    if (gpsWatchId !== null) return; // sudah jalan

    // Minta GPS
    if ('geolocation' in self) {
        gpsWatchId = self.geolocation?.watchPosition(
            onGPSUpdate,
            onGPSError,
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 2000 }
        );
    }

    // Kirim tiap interval
    sendInterval = setInterval(sendPosition, GPS_INTERVAL);
}

function stopTracking() {
    if (gpsWatchId !== null) {
        self.geolocation?.clearWatch(gpsWatchId);
        gpsWatchId = null;
    }
    if (sendInterval !== null) {
        clearInterval(sendInterval);
        sendInterval = null;
    }
    currentPos = null;
}

function onGPSUpdate(position) {
    currentPos = position.coords;
    // Broadcast ke semua tab yang terbuka
    self.clients.matchAll().then(clients => {
        clients.forEach(client => client.postMessage({
            type: 'GPS_UPDATE',
            lat:  currentPos.latitude,
            lng:  currentPos.longitude,
            speed: currentPos.speed ? currentPos.speed * 3.6 : 0,
        }));
    });
}

function onGPSError(err) {
    self.clients.matchAll().then(clients => {
        clients.forEach(client => client.postMessage({
            type:  'GPS_ERROR',
            code:  err.code,
            message: err.message,
        }));
    });
}

async function sendPosition() {
    if (!config.active || !config.serverUrl || !config.deviceId || !currentPos) return;

    const payload = {
        device_id:     config.deviceId,
        latitude:      currentPos.latitude,
        longitude:     currentPos.longitude,
        speed_kmh:     currentPos.speed ? parseFloat((currentPos.speed * 3.6).toFixed(2)) : 0,
        heading:       currentPos.heading  ?? null,
        accuracy_m:    currentPos.accuracy ?? null,
        network_type:  '4G',
        gps_timestamp: new Date().toISOString(),
    };

    try {
        await fetch(`${config.serverUrl}/api/telemetry`, {
            method:  'POST',
            headers: {
                'Content-Type':               'application/json',
                'Accept':                     'application/json',
                'ngrok-skip-browser-warning': 'true',
            },
            body: JSON.stringify(payload),
        });

        // Broadcast status sukses ke semua tab
        self.clients.matchAll().then(clients => {
            clients.forEach(client => client.postMessage({
                type: 'SEND_SUCCESS',
                time: new Date().toLocaleTimeString('id-ID'),
                lat:  currentPos.latitude,
                lng:  currentPos.longitude,
                speed: payload.speed_kmh,
            }));
        });
    } catch(e) {
        self.clients.matchAll().then(clients => {
            clients.forEach(client => client.postMessage({
                type:    'SEND_ERROR',
                message: e.message,
            }));
        });
    }
}