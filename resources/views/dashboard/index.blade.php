@extends('layouts.app')
@section('title', 'Dashboard — Greenfields')

@push('styles')
<style>
    .dash-wrap {
        position: relative;
        height: calc(100vh - 3.5rem);
        overflow: hidden;
    }
    /* Kedua map fullscreen absolute agar bisa switch */
    #dash-osm,
    #dash-gmap {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }
    #dash-gmap { display: none; }

    .fleet-card {
        display: flex; align-items: center; gap: 1rem;
        background: white; border-radius: .75rem;
        border: 1.5px solid #f1f5f9; padding: .875rem;
        cursor: pointer; transition: all .15s;
        text-decoration: none; color: inherit;
        margin-bottom: 8px;
    }
    .fleet-card:last-child { margin-bottom: 0; }
    .fleet-card:hover { border-color: #86efac; box-shadow: 0 2px 8px rgba(34,197,94,.1); }
</style>
@endpush

@section('content')
<div class="dash-wrap">

    {{-- OpenStreetMap --}}
    <div id="dash-osm"></div>

    {{-- Google Maps --}}
    <div id="dash-gmap"></div>

    {{-- Fleet Card Overlay --}}
    <div style="position:absolute;bottom:16px;left:16px;z-index:1000;"
         class="w-[220px] sm:w-[260px]">
        <div style="background:white;border-radius:1.25rem;box-shadow:0 8px 32px rgba(0,0,0,.13);border:1px solid #f1f5f9;padding:.875rem;"
             class="md:p-4">
            <div style="font-size:14px;font-weight:800;color:#111827;margin-bottom:12px;">Active Fleet</div>

            <a href="{{ route('livemap.index') }}" class="fleet-card">
                <div style="width:40px;height:40px;background:#dcfce7;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:700;color:#111827;">Moving</div>
                    <div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;">Dalam Perjalanan</div>
                </div>
                <span id="cnt-moving" style="font-size:20px;font-weight:800;color:#16a34a;">{{ $fleetSummary->moving ?? 0 }}</span>
            </a>

            <a href="{{ route('livemap.index') }}" class="fleet-card">
                <div style="width:40px;height:40px;background:#ffedd5;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="#c2410c" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:700;color:#111827;">Idle</div>
                    <div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;">Tidak Bergerak</div>
                </div>
                <span id="cnt-idle" style="font-size:20px;font-weight:800;color:#c2410c;">{{ $fleetSummary->idle ?? 0 }}</span>
            </a>

            <a href="{{ route('livemap.index') }}" class="fleet-card">
                <div style="width:40px;height:40px;background:#fee2e2;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="#b91c1c" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:700;color:#111827;">Offline</div>
                    <div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;">Perangkat Mati</div>
                </div>
                <span id="cnt-offline" style="font-size:20px;font-weight:800;color:#b91c1c;">{{ $fleetSummary->offline ?? 0 }}</span>
            </a>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const GMAPS_KEY    = "{{ $googleMapsKey ?? '' }}";
const MAP_TYPE     = "{{ $mapType ?? 'osm' }}";
const positions    = @json($vehiclePositions);
const STATUS_COLOR = { moving:'#22c55e', idle:'#f97316', offline:'#ef4444', online:'#22c55e' };

// ════════════════════════════════════════════════════════════════
// OSM
// ════════════════════════════════════════════════════════════════
let osmMap     = null;
let osmMarkers = {};

function initOSM() {
    if (osmMap) return;
    osmMap = L.map('dash-osm', { zoomControl:false }).setView([-7.965, 112.60], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:'© OpenStreetMap contributors', maxZoom:19
    }).addTo(osmMap);
    L.control.zoom({ position:'bottomright' }).addTo(osmMap);
    renderOSMMarkers();
}

function mkOsmIcon(status) {
    const c = STATUS_COLOR[status] || '#6b7280';
    return L.divIcon({
        html:`<div style="width:42px;height:42px;background:${c};border-radius:50%;border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,.22);display:flex;align-items:center;justify-content:center;font-size:19px;">🚛</div>`,
        iconSize:[42,42], iconAnchor:[21,21], className:''
    });
}

function renderOSMMarkers() {
    positions.forEach(v => {
        if (!v.latitude || !v.longitude) return;
        const m = L.marker([+v.latitude, +v.longitude], { icon: mkOsmIcon(v.vehicle_status) })
            .addTo(osmMap)
            .bindPopup(makePopupHTML(v));
        m.on('click', () => window.location.href = `/live-map/${v.vehicle_id}`);
        osmMarkers[v.vehicle_id] = { marker: m };
    });
    const coords = positions.filter(v=>v.latitude&&v.longitude).map(v=>[+v.latitude,+v.longitude]);
    if (coords.length) osmMap.fitBounds(coords, { padding:[50,50] });
}

function makePopupHTML(v) {
    return `<div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;min-width:160px;">
        <div style="font-weight:800;font-size:13px;">${v.vehicle_name}</div>
        <div style="color:#9ca3af;font-size:10px;margin-bottom:6px;">${v.license_plate}</div>
        <div style="font-size:11px;line-height:1.8;">👤 ${v.driver_name||'—'}<br>⚡ ${v.speed_kmh||0} km/h</div>
        <a href="/live-map/${v.vehicle_id}" style="display:block;margin-top:8px;background:#22c55e;color:white;text-align:center;padding:6px;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;">Lihat Detail →</a>
    </div>`;
}

// ── WebSocket handler — update marker dashboard ───────────────────
window.updateDashboardMarker = function(data) {
    const lat = +data.latitude, lng = +data.longitude;
    const vid = data.vehicle_id;

    // Update OSM
    if (osmMarkers[vid]) {
        osmMarkers[vid].marker.setLatLng([lat, lng]);
        osmMarkers[vid].marker.setIcon(mkOsmIcon(data.vehicle_status));
    } else {
        // Marker baru (device baru ditambahkan)
        const m = L.marker([lat, lng], { icon: mkOsmIcon(data.vehicle_status) })
            .addTo(osmMap)
            .bindPopup(makePopupHTML(data));
        m.on('click', () => window.location.href = `/live-map/${vid}`);
        osmMarkers[vid] = { marker: m };
    }

    // Update Google Maps
    if (gMarkers[vid] && gMapReady) {
        gMarkers[vid].setPosition({ lat, lng });
        gMarkers[vid].setIcon(mkGIcon(data.vehicle_status));
    }
};

// ════════════════════════════════════════════════════════════════
// GOOGLE MAPS
// ════════════════════════════════════════════════════════════════
let gMap      = null;
let gMapReady = false;
let gMarkers  = {};
let gTraffic  = null;

function initGMaps() {
    if (!GMAPS_KEY) return;
    if (window.google && window.google.maps) { onDashGmapReady(); return; }
    if (document.getElementById('gmaps-sdk')) return;
    const s = document.createElement('script');
    s.id    = 'gmaps-sdk';
    s.src   = `https://maps.googleapis.com/maps/api/js?key=${GMAPS_KEY}&callback=onDashGmapReady&loading=async`;
    s.async = true; s.defer = true;
    document.head.appendChild(s);
}

function mkGIcon(status) {
    const c   = STATUS_COLOR[status] || '#6b7280';
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="54" height="54" viewBox="0 0 54 54">
        <circle cx="27" cy="27" r="27" fill="${c}" fill-opacity="0.22"/>
        <circle cx="27" cy="27" r="22" fill="${c}" stroke="white" stroke-width="3.5"/>
        <text x="27" y="35" text-anchor="middle" font-size="22" font-family="Apple Color Emoji,Segoe UI Emoji,Noto Color Emoji,sans-serif">🚛</text>
    </svg>`;
    return {
        url:        'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(54, 54),
        anchor:     new google.maps.Point(27, 27),
    };
}

window.onDashGmapReady = function () {
    gMapReady = true;
    const validPos = positions.filter(v => v.latitude && v.longitude);
    const cLat = validPos.length ? validPos.reduce((s,v)=>s+(+v.latitude),0)/validPos.length  : -7.965;
    const cLng = validPos.length ? validPos.reduce((s,v)=>s+(+v.longitude),0)/validPos.length : 112.60;

    gMap = new google.maps.Map(document.getElementById('dash-gmap'), {
        center:{lat:cLat,lng:cLng}, zoom:13, mapTypeId:'roadmap',
        mapTypeControl:false, fullscreenControl:false, streetViewControl:false,
        zoomControlOptions:{position:google.maps.ControlPosition.RIGHT_BOTTOM},
    });

    gTraffic = new google.maps.TrafficLayer();
    gTraffic.setMap(gMap);

    validPos.forEach(v => {
        const marker = new google.maps.Marker({
            position:{lat:+v.latitude,lng:+v.longitude}, map:gMap,
            icon:mkGIcon(v.vehicle_status), title:v.vehicle_name,
            optimized:false, zIndex:10,
        });
        const info = new google.maps.InfoWindow({ content: makePopupHTML(v) });
        marker.addListener('click', () => info.open(gMap, marker));
        gMarkers[v.vehicle_id] = marker;
    });

    if (validPos.length) {
        const bounds = new google.maps.LatLngBounds();
        validPos.forEach(v => bounds.extend({lat:+v.latitude,lng:+v.longitude}));
        gMap.fitBounds(bounds, 60);
    }
};

// ════════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════════
if (MAP_TYPE === 'gmaps') {
    document.getElementById('dash-osm').style.display  = 'none';
    document.getElementById('dash-gmap').style.display = 'block';
    initGMaps();
} else {
    document.getElementById('dash-osm').style.display  = 'block';
    document.getElementById('dash-gmap').style.display = 'none';
    initOSM();
}

// Fallback polling tiap 30 detik (jika WebSocket putus)
setInterval(() => {
    fetch('/api/internal/fleet-summary')
        .then(r=>r.json())
        .then(d=>{
            const active = (d.moving||0)+(d.idle||0);
            const total  = d.total_vehicles||0;
            ['cnt-moving','cnt-idle','cnt-offline'].forEach((id,i) => {
                const el = document.getElementById(id);
                if (el) el.textContent = [d.moving,d.idle,d.offline][i]||0;
            });
        }).catch(()=>{});
}, 30000);
</script>
@endpush