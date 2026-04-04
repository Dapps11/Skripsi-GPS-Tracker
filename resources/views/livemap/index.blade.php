@extends('layouts.app')
@section('title', 'Live Map — Greenfields')

@push('styles')
<style>
    #live-map { width: 100%; height: 100%; }
    .map-wrap { position: relative; flex: 1; overflow: hidden; }
    .detail-panel {
        width: 340px; flex-shrink: 0; background: white;
        border-left: 1px solid #f1f5f9; overflow-y: auto;
        display: flex; flex-direction: column;
    }
    .irow {
        display: flex; align-items: center; justify-content: space-between;
        padding: 11px 0; border-bottom: 1px solid #f9fafb;
    }
    .irow:last-child { border-bottom: none; }
</style>
@endpush

@section('content')
<div class="flex" style="height: calc(100vh - 3.5rem);">

    {{-- Map --}}
    <div class="map-wrap">
        <div id="live-map"></div>
    </div>

    {{-- Detail Panel --}}
    @if(isset($trip) && $trip)
    <div class="detail-panel">
        <div class="p-5 flex-1 overflow-y-auto">

            {{-- Header --}}
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded-full uppercase tracking-wide">Active</span>
                        <span class="text-base font-extrabold text-gray-900">{{ $trip->vehicle_name }}</span>
                    </div>
                    <p class="text-sm text-gray-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ $trip->driver_name }} (Driver)
                    </p>
                </div>
                <a href="{{ route('livemap.index') }}" class="text-gray-300 hover:text-gray-500 transition-colors mt-1">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </a>
            </div>

            {{-- Route --}}
            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                <div class="flex gap-3">
                    <div class="flex flex-col items-center pt-0.5">
                        <div class="w-3 h-3 bg-green-500 rounded-full border-2 border-white shadow"></div>
                        <div class="w-px flex-1 bg-gray-200 my-1.5"></div>
                        <div class="w-3 h-3 bg-gray-400 rounded-full border-2 border-white shadow"></div>
                    </div>
                    <div class="flex-1 space-y-3">
                        <div>
                            <div class="text-[9px] font-bold text-green-600 uppercase tracking-wider">Start Point</div>
                            <div class="text-sm font-semibold text-gray-900">{{ $trip->origin_name }}</div>
                            <div class="text-xs text-gray-400">
                                Departed: {{ \Carbon\Carbon::parse($trip->departed_at)->format('h:i A') }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Destination</div>
                            <div class="text-sm font-semibold text-gray-900">{{ $trip->dest_name }}</div>
                            <div class="text-xs text-gray-400">
                                Est. Arrival: {{ \Carbon\Carbon::parse($trip->estimated_arrival_at)->format('h:i A') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Vehicle Info --}}
            <div class="grid grid-cols-2 gap-2 mb-4">
                <div class="bg-gray-50 rounded-xl p-3">
                    <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wider mb-1">Vehicle Type</div>
                    <div class="text-sm font-semibold text-gray-900">{{ $trip->vehicle_type }}</div>
                </div>
                <div class="bg-gray-50 rounded-xl p-3">
                    <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wider mb-1">License Plate</div>
                    <div class="text-sm font-bold text-gray-900">{{ $trip->license_plate }}</div>
                </div>
            </div>

            {{-- IoT Status --}}
            <div class="mb-4">
                <div class="flex items-center gap-1.5 mb-3">
                    <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Real-Time IoT Status</span>
                </div>

                <div>
                    {{-- Speed --}}
                    <div class="irow">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <span class="text-sm text-gray-700">Current Speed</span>
                        </div>
                        <span class="text-sm font-bold text-gray-900">
                            <span id="live-speed">{{ $trip->current_speed_kmh ?? 0 }}</span>
                            <span class="text-xs font-normal text-gray-400"> kmh</span>
                        </span>
                    </div>

                    {{-- ETA --}}
                    <div class="irow">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 bg-orange-50 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <span class="text-sm text-gray-700">Estimated Time of Arrival</span>
                        </div>
                        @php
                            $etaMin = $trip->estimated_arrival_at
                                ? max(0, now()->diffInMinutes(\Carbon\Carbon::parse($trip->estimated_arrival_at), false))
                                : '–';
                        @endphp
                        <span class="text-sm font-bold text-gray-900">
                            {{ $etaMin }}<span class="text-xs font-normal text-gray-400"> min</span>
                        </span>
                    </div>

                    {{-- Driver Status --}}
                    <div class="irow">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <span class="text-sm text-gray-700">Driver Status</span>
                        </div>
                        @php
                            $ds  = $latestDriverStatus->driver_status ?? 'normal';
                            $dsCls = match($ds) {
                                'warning' => 'background:#ffedd5;color:#c2410c',
                                'danger'  => 'background:#fee2e2;color:#b91c1c',
                                default   => 'background:#dcfce7;color:#15803d',
                            };
                        @endphp
                        <span id="ds-badge" style="padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:700;{{ $dsCls }}">
                            {{ strtoupper($ds) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Alert box --}}
            @if(isset($latestDriverStatus) && $latestDriverStatus && $latestDriverStatus->driver_status !== 'normal')
            <div class="p-3 bg-orange-50 border border-orange-200 rounded-xl flex gap-2 mb-4">
                <svg class="w-4 h-4 text-orange-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="text-xs text-orange-700">System detected drowsy behavior. Monitoring closely.</span>
            </div>
            @endif

            {{-- Actions --}}
            <div class="grid grid-cols-2 gap-2">
                <a href="tel:{{ $trip->driver_phone ?? '' }}"
                   class="flex items-center justify-center gap-2 py-2.5 border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    Contact Driver
                </a>
                <button class="flex items-center justify-center gap-2 py-2.5 bg-green-500 hover:bg-green-600 rounded-xl text-sm font-semibold text-white transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    View Telemetry
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
const allVehicles  = @json($vehicles);
const gpsPoints    = @json($gpsPoints    ?? []);
const activeTrip   = @json($trip         ?? null);

const liveMap = L.map('live-map', { zoomControl: false }).setView([-7.965, 112.60], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors', maxZoom: 19
}).addTo(liveMap);

L.control.zoom({ position: 'topleft' }).addTo(liveMap);

const colors = { moving: '#22c55e', idle: '#f97316', offline: '#ef4444', online: '#22c55e' };

function mkIcon(status) {
    const c = colors[status] || '#6b7280';
    return L.divIcon({
        html: `<div style="width:38px;height:38px;background:${c};border-radius:50%;border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,0.25);display:flex;align-items:center;justify-content:center;font-size:17px">🚛</div>`,
        iconSize: [38,38], iconAnchor: [19,19], className: ''
    });
}

// Render semua marker kendaraan
allVehicles.forEach(v => {
    if (!v.latitude || !v.longitude) return;
    L.marker([v.latitude, v.longitude], { icon: mkIcon(v.vehicle_status) })
     .addTo(liveMap)
     .bindPopup(`<b>${v.vehicle_name}</b><br>${v.license_plate}<br>
         <a href="/live-map/${v.vehicle_id}" style="color:#22c55e;font-weight:700">Lihat Detail →</a>`)
     .on('click', () => { window.location.href = `/live-map/${v.vehicle_id}`; });
});

// Jika ada trip aktif → gambar rute
if (activeTrip && gpsPoints.length >= 2) {
    const coords = gpsPoints.map(p => [p.latitude, p.longitude]);

    L.polyline(coords, { color: '#4f46e5', weight: 4, opacity: 0.85 }).addTo(liveMap);

    // Marker asal
    const originIcon = L.divIcon({
        html: `<div style="width:14px;height:14px;background:#22c55e;border-radius:50%;border:3px solid white;box-shadow:0 0 0 3px rgba(34,197,94,0.3)"></div>`,
        iconSize: [14,14], iconAnchor: [7,7], className: ''
    });
    L.marker([activeTrip.origin_lat, activeTrip.origin_lng], { icon: originIcon })
     .addTo(liveMap)
     .bindTooltip(`<b>${activeTrip.origin_name}</b>`, { permanent: true, direction: 'top', offset: [0, -8] });

    // Marker tujuan
    const destIcon = L.divIcon({
        html: `<div style="width:14px;height:14px;background:#6366f1;border-radius:50%;border:3px solid white;box-shadow:0 0 0 3px rgba(99,102,241,0.3)"></div>`,
        iconSize: [14,14], iconAnchor: [7,7], className: ''
    });
    L.marker([activeTrip.dest_lat, activeTrip.dest_lng], { icon: destIcon })
     .addTo(liveMap)
     .bindTooltip(`<b>${activeTrip.dest_name}</b>`, { permanent: true, direction: 'bottom', offset: [0, 8] });

    liveMap.fitBounds(coords, { padding: [80, 80] });

} else {
    const coords = allVehicles.filter(v => v.latitude && v.longitude).map(v => [v.latitude, v.longitude]);
    if (coords.length) liveMap.fitBounds(coords, { padding: [50, 50] });
}
</script>
@endpush