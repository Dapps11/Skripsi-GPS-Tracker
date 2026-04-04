@extends('layouts.app')
@section('title', 'Dashboard — Greenfields')

@push('styles')
<style>
    #dash-map { height: calc(100vh - 3.5rem); }
    .fleet-card {
        display: flex; align-items: center; gap: 1rem;
        background: white; border-radius: 0.75rem;
        border: 1px solid #f1f5f9; padding: 0.875rem;
        cursor: pointer; transition: all 0.15s;
        text-decoration: none; color: inherit;
    }
    .fleet-card:hover { border-color: #86efac; box-shadow: 0 2px 8px rgba(34,197,94,0.1); }
</style>
@endpush

@section('content')
<div class="relative" style="height: calc(100vh - 3.5rem);">

    {{-- Map fullscreen --}}
    <div id="dash-map" class="w-full h-full"></div>

    {{-- Fleet Card Overlay --}}
    <div class="absolute bottom-6 left-6 z-[1000]">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-4 w-64">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-bold text-gray-900">Active Fleet</span>
                <span class="text-gray-300">···</span>
            </div>

            <a href="{{ route('livemap.index') }}" class="fleet-card mb-2">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="text-sm font-bold text-gray-900">Moving</div>
                    <div class="text-[10px] text-gray-400 uppercase tracking-wide">Dalam Perjalanan</div>
                </div>
                <span id="cnt-moving" class="text-xl font-extrabold text-green-600">{{ $fleetSummary->moving ?? 0 }}</span>
            </a>

            <a href="{{ route('livemap.index') }}" class="fleet-card mb-2">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="text-sm font-bold text-gray-900">Idle</div>
                    <div class="text-[10px] text-gray-400 uppercase tracking-wide">Tidak Bergerak</div>
                </div>
                <span id="cnt-idle" class="text-xl font-extrabold text-orange-500">{{ $fleetSummary->idle ?? 0 }}</span>
            </a>

            <a href="{{ route('livemap.index') }}" class="fleet-card">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="text-sm font-bold text-gray-900">Offline</div>
                    <div class="text-[10px] text-gray-400 uppercase tracking-wide">Perangkat Mati</div>
                </div>
                <span id="cnt-offline" class="text-xl font-extrabold text-red-500">{{ $fleetSummary->offline ?? 0 }}</span>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const positions = @json($vehiclePositions);
const colors    = { moving: '#22c55e', idle: '#f97316', offline: '#ef4444', online: '#22c55e' };

const map = L.map('dash-map', { zoomControl: false }).setView([-7.965, 112.60], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors', maxZoom: 19
}).addTo(map);

L.control.zoom({ position: 'bottomright' }).addTo(map);

function mkIcon(status) {
    const c = colors[status] || '#6b7280';
    return L.divIcon({
        html: `<div style="width:38px;height:38px;background:${c};border-radius:50%;border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,0.25);display:flex;align-items:center;justify-content:center;font-size:17px">🚛</div>`,
        iconSize: [38,38], iconAnchor: [19,19], className: ''
    });
}

const markers = {};
positions.forEach(v => {
    if (!v.latitude || !v.longitude) return;
    const m = L.marker([v.latitude, v.longitude], { icon: mkIcon(v.vehicle_status) })
        .addTo(map)
        .bindPopup(`
            <div style="font-family:'Plus Jakarta Sans',sans-serif;min-width:160px;font-size:13px">
                <div style="font-weight:700">${v.vehicle_name}</div>
                <div style="color:#9ca3af;font-size:11px">${v.license_plate}</div>
                <div style="margin:6px 0;font-size:11px;color:#374151">
                    👤 ${v.driver_name || '—'}<br>⚡ ${v.speed_kmh || 0} km/h
                </div>
                <a href="/live-map/${v.vehicle_id}"
                   style="display:block;background:#22c55e;color:white;text-align:center;padding:5px 10px;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none">
                    Lihat Detail →
                </a>
            </div>`);
    markers[v.vehicle_id] = m;
});

const coords = positions.filter(v => v.latitude && v.longitude).map(v => [v.latitude, v.longitude]);
if (coords.length) map.fitBounds(coords, { padding: [50, 50] });

async function pollPositions() {
    try {
        const data = await (await fetch('/api/internal/vehicles-position')).json();
        data.forEach(v => {
            if (!v.latitude || !v.longitude) return;
            if (markers[v.vehicle_id]) {
                markers[v.vehicle_id].setLatLng([v.latitude, v.longitude]);
                markers[v.vehicle_id].setIcon(mkIcon(v.vehicle_status));
            }
        });
    } catch(e) {}
}

async function pollFleet() {
    try {
        const d = await (await fetch('/api/internal/fleet-summary')).json();
        document.getElementById('cnt-moving').textContent  = d.moving  || 0;
        document.getElementById('cnt-idle').textContent    = d.idle    || 0;
        document.getElementById('cnt-offline').textContent = d.offline || 0;
    } catch(e) {}
}

setInterval(pollPositions, 15000);
setInterval(pollFleet, 15000);
</script>
@endpush