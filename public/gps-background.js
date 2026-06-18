// public/gps-background.js
// Diload di semua halaman — handle GPS persistence

const GPS_KEY      = 'gf_gps_active';
const GPS_DATA_KEY = 'gf_gps_data';
const GPS_CFG_KEY  = 'gf_gps_config';

class BackgroundGPS {
    constructor() {
        this.watchId      = null;
        this.sendInterval = null;
        this.currentPos   = null;
        this.channel      = new BroadcastChannel('gps_channel');
        this.isActive     = false;

        // Cek apakah GPS seharusnya aktif (dari session sebelumnya)
        this.resumeIfActive();

        // Listen broadcast dari tab lain
        this.channel.onmessage = (e) => this.onChannelMessage(e.data);

        // Sync storage antar tab
        window.addEventListener('storage', (e) => {

            if (e.key === GPS_KEY) {

                console.log('[GPS STORAGE]', e.newValue);

               if (e.newValue === 'true') {

                    this.resumeIfActive();

                } else {

                    this.isActive = false;
                }
            }
        });
    }

    resumeIfActive() {
        const isActive = localStorage.getItem(GPS_KEY) === 'true';
        if (isActive && !this.isActive) {
            const cfg = JSON.parse(localStorage.getItem(GPS_CFG_KEY) || '{}');
            if (cfg.deviceId && cfg.serverUrl) {
                this.start(cfg.serverUrl, cfg.deviceId, cfg.intervalMs || 5000);
            }
        }
    }

    start(serverUrl, deviceId, intervalMs = 3000) {
        if (this.isActive) return;
        // this.isActive = true;
        this.serverUrl  = serverUrl;
        this.deviceId   = deviceId;
        this.intervalMs = intervalMs;

        // Simpan config ke localStorage agar tab lain bisa resume
        localStorage.setItem(GPS_KEY,     'true');
        localStorage.setItem(GPS_CFG_KEY, JSON.stringify({ serverUrl, deviceId, intervalMs }));

        // Start GPS watch
        if (!('geolocation' in navigator)) {
            console.warn('Geolocation not supported');
            return;
        }

        this.watchId = navigator.geolocation.watchPosition(
            (pos) => this.onGPSUpdate(pos),
            (err) => this.onGPSError(err),
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 2000 }
        );

        // Kirim tiap interval
        this.sendInterval = setInterval(() => this.sendPosition(), intervalMs);

        console.log('[BackgroundGPS] Started', deviceId);
    }

    stop() {
        this.isActive = false;
        localStorage.setItem(GPS_KEY, 'false');
        localStorage.removeItem(GPS_CFG_KEY);

        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
        if (this.sendInterval !== null) {
            clearInterval(this.sendInterval);
            this.sendInterval = null;
        }

        this.channel.postMessage({ type: 'GPS_STOPPED' });
        console.log('[BackgroundGPS] Stopped');
    }

    onGPSUpdate(pos) {
        this.currentPos = pos.coords;

        // Simpan ke localStorage agar bisa dibaca tab lain
        localStorage.setItem(GPS_DATA_KEY, JSON.stringify({
            lat:       pos.coords.latitude,
            lng:       pos.coords.longitude,
            speed:     pos.coords.speed ? pos.coords.speed * 3.6 : 0,
            accuracy:  pos.coords.accuracy,
            heading:   pos.coords.heading,
            timestamp: new Date().toISOString(),
        }));

        // Broadcast ke semua tab
        this.channel.postMessage({
            type:     'GPS_UPDATE',
            lat:      pos.coords.latitude,
            lng:      pos.coords.longitude,
            speed:    pos.coords.speed ? pos.coords.speed * 3.6 : 0,
            accuracy: pos.coords.accuracy,
        });
    }

    onGPSError(err) {
        this.channel.postMessage({ type: 'GPS_ERROR', code: err.code, message: err.message });
    }

    async sendPosition() {
        if (!this.isActive || !this.currentPos) return;

        const payload = {
            device_id:     this.deviceId,
            latitude:      this.currentPos.latitude,
            longitude:     this.currentPos.longitude,
            speed_kmh:     this.currentPos.speed
                ? parseFloat((this.currentPos.speed * 3.6).toFixed(2))
                : 0,
            heading:       this.currentPos.heading  ?? null,
            accuracy_m:    this.currentPos.accuracy ?? null,
            network_type:  '4G',
            gps_timestamp: new Date().toISOString(),
        };

        try {
            const res = await fetch(`${this.serverUrl}/api/telemetry`, {
                method:  'POST',
                headers: {
                    'Content-Type':               'application/json',
                    'Accept':                     'application/json',
                    'ngrok-skip-browser-warning': 'true',
                },
                body: JSON.stringify(payload),
            });

            const json = await res.json();
            this.channel.postMessage({
                type:  json.ok ? 'SEND_SUCCESS' : 'SEND_ERROR',
                time:  new Date().toLocaleTimeString('id-ID'),
                lat:   payload.latitude,
                lng:   payload.longitude,
                speed: payload.speed_kmh,
            });
        } catch(e) {
            this.channel.postMessage({ type: 'SEND_ERROR', message: e.message });
        }
    }

    onChannelMessage(data) {
        // Tab lain minta stop → stop di tab ini juga
        if (data.type === 'GPS_STOPPED') {
            if (this.watchId !== null) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
            if (this.sendInterval !== null) {
                clearInterval(this.sendInterval);
                this.sendInterval = null;
            }
            this.isActive = false;
        }
    }

    // Status GPS saat ini
    getStatus() {
        return {
            active: this.isActive,
            pos:    this.currentPos ? {
                lat:   this.currentPos.latitude,
                lng:   this.currentPos.longitude,
                speed: this.currentPos.speed ? this.currentPos.speed * 3.6 : 0,
            } : null,
        };
    }
}

// Inisialisasi global
window.backgroundGPS = new BackgroundGPS();

window.addEventListener('DOMContentLoaded', () => {

    const gpsIndicator =
        document.getElementById('gps-indicator');

    const gpsIndSpeed =
        document.getElementById('gps-ind-speed');

    console.log('[GPS] Header indicator initialized');

    // Cek tiap detik apakah GPS aktif
    setInterval(() => {

        if (!window.backgroundGPS) return;

        const status =
            window.backgroundGPS.getStatus();

        console.log('[GPS STATUS]', status);

        if (gpsIndicator) {
            gpsIndicator.style.display = 'flex';
        }

        if (
            status.active &&
            status.pos &&
            gpsIndSpeed
        ) {
            gpsIndSpeed.textContent =
                `${status.pos.speed.toFixed(0)} km/h`;
        }

    }, 1000);

    // Listen realtime GPS update
    const headerGpsChannel =
        new BroadcastChannel('gps_channel');

    headerGpsChannel.onmessage = (e) => {

        console.log('[GPS CHANNEL]', e.data);

        if (e.data.type === 'GPS_UPDATE') {

            if (gpsIndicator) {
                gpsIndicator.style.display = 'flex';
            }

            if (gpsIndSpeed) {
                gpsIndSpeed.textContent =
                    `${e.data.speed.toFixed(0)} km/h`;
            }
        }

        if (e.data.type === 'GPS_STOPPED') {

            if (gpsIndicator) {
                gpsIndicator.style.display = 'none';
            }
        }
    };
});