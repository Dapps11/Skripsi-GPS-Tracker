@extends('layouts.app')
@section('title', 'GPS Tester — Greenfields')

@push('styles')
<style>
    .log-item      { padding:4px 0; font-size:11px; font-family:monospace; border-bottom:1px solid #f3f4f6; line-height:1.6; }
    .log-ok        { color:#16a34a; }
    .log-err       { color:#dc2626; }
    .log-warn      { color:#d97706; }
    .log-info      { color:#6b7280; }
    #log-container { max-height:220px; overflow-y:auto; }

    .stat-pill {
        display:flex; flex-direction:column; align-items:center;
        background:#f9fafb; border-radius:12px; padding:12px 16px;
        min-width:80px; border:1.5px solid #f1f5f9;
    }
    .stat-pill .val { font-size:20px; font-weight:800; color:#111827; line-height:1.2; }
    .stat-pill .lbl { font-size:9px;  font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; margin-top:3px; }

    .coord-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f9fafb; }
    .coord-row:last-child { border-bottom:none; }
    .coord-lbl { font-size:11px; color:#9ca3af; font-weight:600; }
    .coord-val { font-size:12px; font-family:monospace; color:#111827; font-weight:700; }

    @keyframes gps-pulse {
        0%,100% { opacity:1; transform:scale(1); }
        50%      { opacity:.5; transform:scale(1.4); }
    }
    .gps-active { animation:gps-pulse 1.5s infinite; }
</style>
@endpush

@section('content')
<div class="p-4 md:p-6 max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900">GPS Tester</h1>
            <p class="text-sm text-gray-400 mt-0.5">Simulasi pengiriman data GPS dari device ke server</p>
        </div>
        {{-- GPS Status Badge --}}
        <div id="gps-status-badge"
             style="display:flex;align-items:center;gap:8px;padding:8px 16px;background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:12px;">
            <span id="gps-dot" style="width:9px;height:9px;background:#9ca3af;border-radius:50%;flex-shrink:0;transition:background .3s;"></span>
            <span id="gps-status-text" style="font-size:12px;font-weight:600;color:#6b7280;">GPS tidak aktif</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        {{-- ── KOLOM KIRI: Konfigurasi ── --}}
        <div class="md:col-span-1 space-y-4">

            {{-- Config Card --}}
            <div class="card p-4">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Konfigurasi</div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Server URL</label>
                        <input type="text" id="server-url"
                               class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 font-mono"
                               placeholder="http://192.168.x.x:8000">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Device</label>
                        <select id="device-id"
                                class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white">
                            @forelse($devices as $d)
                            <option value="{{ $d->device_id }}">
                                {{ $d->device_id }}
                                @if($d->vehicle) — {{ $d->vehicle->name }} @endif
                                · {{ strtoupper($d->status) }}
                            </option>
                            @empty
                            <option value="" disabled>Belum ada device</option>
                            @endforelse
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Interval Kirim</label>
                        <select id="interval"
                                class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white">
                            <option value="3000">3 detik</option>
                            <option value="5000" selected>5 detik</option>
                            <option value="10000">10 detik</option>
                            <option value="30000">30 detik</option>
                        </select>
                    </div>
                </div>

                {{-- Buttons --}}
                <div class="flex gap-2 mt-4">
                    <button id="btn-start" onclick="startTracking()"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;background:#22c55e;border:none;border-radius:10px;font-size:12px;font-weight:700;color:white;cursor:pointer;">
                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Mulai
                    </button>
                    <button id="btn-stop" onclick="stopTracking()"
                            style="display:none;flex:1;align-items:center;justify-content:center;gap:6px;padding:10px;background:#ef4444;border:none;border-radius:10px;font-size:12px;font-weight:700;color:white;cursor:pointer;">
                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                        </svg>
                        Stop
                    </button>
                    <button onclick="sendOnce()"
                            style="padding:10px 12px;background:#f3f4f6;border:none;border-radius:10px;font-size:12px;font-weight:600;color:#374151;cursor:pointer;"
                            title="Kirim 1x">
                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Stats --}}
            <div class="card p-4">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Statistik</div>
                <div class="flex gap-2 mb-3">
                    <div class="stat-pill flex-1">
                        <div class="val text-green-600" id="stat-ok">0</div>
                        <div class="lbl">Berhasil</div>
                    </div>
                    <div class="stat-pill flex-1">
                        <div class="val text-red-500" id="stat-err">0</div>
                        <div class="lbl">Gagal</div>
                    </div>
                    <div class="stat-pill flex-1">
                        <div class="val" id="stat-total">0</div>
                        <div class="lbl">Total</div>
                    </div>
                </div>
                <div class="coord-row">
                    <span class="coord-lbl">Terakhir kirim</span>
                    <span class="coord-val text-gray-500" id="last-sent">—</span>
                </div>
            </div>

        </div>

        {{-- ── KOLOM KANAN: Koordinat + Log ── --}}
        <div class="md:col-span-2 space-y-4">

            {{-- Koordinat GPS --}}
            <div class="card p-4">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Data GPS Real-time</div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3">
                    <div>
                        <div class="text-[9px] font-bold text-gray-400 uppercase mb-1">Latitude</div>
                        <div class="font-mono text-sm font-bold text-gray-900" id="disp-lat">—</div>
                    </div>
                    <div>
                        <div class="text-[9px] font-bold text-gray-400 uppercase mb-1">Longitude</div>
                        <div class="font-mono text-sm font-bold text-gray-900" id="disp-lng">—</div>
                    </div>
                    <div>
                        <div class="text-[9px] font-bold text-gray-400 uppercase mb-1">Speed</div>
                        <div class="font-mono text-sm font-bold text-gray-900">
                            <span id="disp-speed">—</span>
                            <span class="text-gray-400 text-xs font-normal"> km/h</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-[9px] font-bold text-gray-400 uppercase mb-1">Akurasi</div>
                        <div class="font-mono text-sm font-bold text-gray-900">
                            <span id="disp-acc">—</span>
                            <span class="text-gray-400 text-xs font-normal"> m</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-[9px] font-bold text-gray-400 uppercase mb-1">Heading</div>
                        <div class="font-mono text-sm font-bold text-gray-900">
                            <span id="disp-hdg">—</span>
                            <span class="text-gray-400 text-xs font-normal">°</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-[9px] font-bold text-gray-400 uppercase mb-1">Timestamp</div>
                        <div class="font-mono text-xs font-bold text-gray-600" id="disp-time">—</div>
                    </div>
                </div>

                {{-- Mini map preview --}}
                <div id="gps-map" style="height:160px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;overflow:hidden;"></div>
            </div>

            {{-- Log --}}
            <div class="card p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Log Aktivitas</div>
                    <button onclick="clearLog()"
                            class="text-xs text-gray-400 hover:text-red-500 font-semibold transition-colors">
                        Hapus log
                    </button>
                </div>
                <div id="log-container"></div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── State ─────────────────────────────────────────────────────────
let watchId      = null;
let sendInterval = null;
let currentPos   = null;
let statOk       = 0;
let statErr      = 0;
let isTracking   = false;
let gpsMap       = null;
let gpsMarker    = null;

// ── Elemen ────────────────────────────────────────────────────────
const elLat    = document.getElementById('disp-lat');
const elLng    = document.getElementById('disp-lng');
const elSpeed  = document.getElementById('disp-speed');
const elAcc    = document.getElementById('disp-acc');
const elHdg    = document.getElementById('disp-hdg');
const elTime   = document.getElementById('disp-time');
const elDot    = document.getElementById('gps-dot');
const elTxt    = document.getElementById('gps-status-text');
const elOk     = document.getElementById('stat-ok');
const elErr    = document.getElementById('stat-err');
const elTotal  = document.getElementById('stat-total');
const elSent   = document.getElementById('last-sent');
const elBtnStart = document.getElementById('btn-start');
const elBtnStop  = document.getElementById('btn-stop');
const elLog    = document.getElementById('log-container');

// ── Auto-detect URL ───────────────────────────────────────────────
document.getElementById('server-url').value = window.location.origin;
addLog(`🌐 Server URL: ${window.location.origin}`, 'info');

// ── Mini Map (Leaflet) ────────────────────────────────────────────
gpsMap = L.map('gps-map', { zoomControl: false, attributionControl: false })
           .setView([-7.965, 112.60], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19 }).addTo(gpsMap);
L.control.zoom({ position: 'topright' }).addTo(gpsMap);

function updateMap(lat, lng) {
    const ll = L.latLng(lat, lng);
    if (!gpsMarker) {
        gpsMarker = L.circleMarker(ll, {
            radius:      8,
            color:       '#22c55e',
            fillColor:   '#22c55e',
            fillOpacity: 1,
            weight:      3,
        }).addTo(gpsMap);
    } else {
        gpsMarker.setLatLng(ll);
    }
    gpsMap.setView(ll, Math.max(gpsMap.getZoom(), 15));
}

// ── Log ───────────────────────────────────────────────────────────
function addLog(msg, type = 'info') {
    const t  = new Date().toLocaleTimeString('id-ID');
    const el = document.createElement('div');
    el.className   = `log-item log-${type}`;
    el.textContent = `[${t}] ${msg}`;
    elLog.prepend(el);
    while (elLog.children.length > 80) elLog.removeChild(elLog.lastChild);
}
function clearLog() {
    elLog.innerHTML = '<div class="log-item log-info">[—] Log dikosongkan.</div>';
}

function setGPSStatus(text, color, pulse = false) {
    if (elDot) {
        elDot.style.background = color;
        elDot.className        = pulse ? 'gps-active' : '';
    }
    if (elTxt) { elTxt.textContent = text; elTxt.style.color = color === '#9ca3af' ? '#6b7280' : color; }
}

function updateStats() {
    if (elOk)    elOk.textContent    = statOk;
    if (elErr)   elErr.textContent   = statErr;
    if (elTotal) elTotal.textContent = statOk + statErr;
}

// ── GPS Watch ─────────────────────────────────────────────────────
function startGPSWatch() {
    if (!('geolocation' in navigator)) {
        addLog('❌ Geolocation tidak didukung!', 'err');
        return false;
    }
    setGPSStatus('Meminta izin GPS...', '#f59e0b', false);
    addLog('📡 Meminta akses GPS...', 'info');

    watchId = navigator.geolocation.watchPosition(
        (pos) => {
            currentPos = pos;
            const c    = pos.coords;

            if (elLat)  elLat.textContent  = c.latitude.toFixed(7);
            if (elLng)  elLng.textContent  = c.longitude.toFixed(7);
            if (elSpeed) elSpeed.textContent = c.speed != null ? (c.speed * 3.6).toFixed(1) : '0.0';
            if (elAcc)  elAcc.textContent  = c.accuracy != null ? c.accuracy.toFixed(0) : '—';
            if (elHdg)  elHdg.textContent  = c.heading  != null ? c.heading.toFixed(0)  : '—';
            if (elTime) elTime.textContent  = new Date(pos.timestamp).toLocaleTimeString('id-ID');

            setGPSStatus(`GPS aktif · ±${(c.accuracy||0).toFixed(0)}m`, '#22c55e', true);
            updateMap(c.latitude, c.longitude);
        },
        (err) => {
            const msgs = { 1:'Izin GPS ditolak', 2:'GPS tidak tersedia', 3:'Timeout GPS' };
            addLog(`⚠️ GPS Error: ${msgs[err.code] || err.message}`, 'warn');
            setGPSStatus(msgs[err.code] || 'GPS Error', '#ef4444', false);
        },
        { enableHighAccuracy: true, timeout: 20000, maximumAge: 2000 }
    );
    return true;
}

// ── Send Position ─────────────────────────────────────────────────
async function sendPosition() {
    if (!currentPos) { addLog('⏳ GPS belum dapat sinyal...', 'warn'); return; }

    const c        = currentPos.coords;
    const deviceId = document.getElementById('device-id').value.trim();
    const baseUrl  = document.getElementById('server-url').value.trim().replace(/\/$/, '');

    const payload = {
        device_id:     deviceId,
        latitude:      parseFloat(c.latitude.toFixed(7)),
        longitude:     parseFloat(c.longitude.toFixed(7)),
        speed_kmh:     c.speed    != null ? parseFloat((c.speed * 3.6).toFixed(2)) : 0,
        heading:       c.heading  != null ? parseFloat(c.heading.toFixed(1))       : null,
        accuracy_m:    c.accuracy != null ? parseFloat(c.accuracy.toFixed(1))      : null,
        network_type:  '4G',
        gps_timestamp: new Date().toISOString(),
    };

    try {
        const res = await fetch(`${baseUrl}/api/telemetry`, {
            method:  'POST',
            headers: {
                'Content-Type':               'application/json',
                'Accept':                     'application/json',
                'ngrok-skip-browser-warning': 'true',
                'X-CSRF-TOKEN':               document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Device-Key':               '{{ config('services.iot.api_key') }}',
            },
            body: JSON.stringify(payload),
        });

        const ct = res.headers.get('content-type') || '';
        if (ct.includes('text/html')) {
            statErr++;
            addLog('❌ Response HTML — ngrok splash? Coba refresh.', 'err');
            updateStats(); return;
        }

        const json = await res.json();
        if (res.ok && json.ok) {
            statOk++;
            if (elSent) elSent.textContent = new Date().toLocaleTimeString('id-ID');
            addLog(`✅ ${payload.latitude.toFixed(6)}, ${payload.longitude.toFixed(6)} | ${payload.speed_kmh} km/h | ${json.status ?? ''}`, 'ok');
        } else {
            statErr++;
            addLog(`❌ Server ${res.status}: ${JSON.stringify(json)}`, 'err');
        }
    } catch(e) {
        statErr++;
        addLog(`❌ ${e.message}`, 'err');
    }
    updateStats();
}

// ── Start / Stop / Send Once ──────────────────────────────────────
function startTracking() {
    if (isTracking) return;
    const url      = document.getElementById('server-url').value.trim();
    const deviceId = document.getElementById('device-id').value.trim();
    if (!url)      { addLog('❌ Server URL kosong!', 'err'); return; }
    if (!deviceId) { addLog('❌ Pilih Device ID!',   'err'); return; }

    const ok = startGPSWatch();
    if (!ok) return;

    const intervalMs = parseInt(document.getElementById('interval').value);
    setTimeout(() => {
        sendPosition();
        sendInterval = setInterval(sendPosition, intervalMs);
    }, 2500);

    isTracking = true;
    elBtnStart.style.display = 'none';
    elBtnStop.style.display  = 'flex';
    addLog(`▶ Tracking dimulai — Device: ${deviceId} | Interval: ${intervalMs/1000}s`, 'info');
}

function stopTracking() {
    if (watchId !== null)      { navigator.geolocation.clearWatch(watchId); watchId = null; }
    if (sendInterval !== null) { clearInterval(sendInterval); sendInterval = null; }
    isTracking = false;
    elBtnStart.style.display = 'flex';
    elBtnStop.style.display  = 'none';
    setGPSStatus('Tracking dihentikan', '#9ca3af', false);
    addLog('⏹ Tracking dihentikan.', 'warn');
}

function sendOnce() {
    const url = document.getElementById('server-url').value.trim();
    if (!url) { addLog('❌ Isi Server URL dulu!', 'err'); return; }
    if (!currentPos) {
        setGPSStatus('Mendapatkan GPS...', '#f59e0b', false);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                currentPos = pos;
                const c    = pos.coords;
                if (elLat)   elLat.textContent   = c.latitude.toFixed(7);
                if (elLng)   elLng.textContent   = c.longitude.toFixed(7);
                if (elSpeed) elSpeed.textContent = c.speed != null ? (c.speed*3.6).toFixed(1) : '0.0';
                if (elAcc)   elAcc.textContent   = c.accuracy?.toFixed(0) ?? '—';
                setGPSStatus('GPS didapat!', '#22c55e', true);
                updateMap(c.latitude, c.longitude);
                sendPosition();
            },
            (err) => addLog(`❌ GPS gagal: ${err.message}`, 'err'),
            { enableHighAccuracy: true, timeout: 15000 }
        );
    } else {
        sendPosition();
    }
}

// ── Wake Lock (cegah layar mati) ──────────────────────────────────
if ('wakeLock' in navigator) {
    navigator.wakeLock.request('screen').catch(() => {});
    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible' && isTracking) {
            try { await navigator.wakeLock.request('screen'); } catch(e) {}
        }
    });
}
</script>
@endpush