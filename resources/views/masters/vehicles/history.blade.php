@extends('layouts.app')
@section('title', 'Riwayat Harian — ' . $vehicle->name)

@push('styles')
<style>
#history-map  { height: 460px; border-radius: 0 0 1rem 1rem; }
#history-gmap { height: 460px; border-radius: 0 0 1rem 1rem; display: none; }
@media (max-width: 768px) {
    #history-map, #history-gmap { height: 300px; }
}
.stat-card { background:#fff; border:1.5px solid #f1f5f9; border-radius:1rem; padding:1rem 1.25rem; }
.stat-val  { font-size:1.5rem; font-weight:800; color:#111827; }
.stat-lbl  { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#9ca3af; margin-top:.15rem; }
.alert-badge { display:inline-block; padding:1px 8px; border-radius:999px; font-size:.65rem; font-weight:700; }
.badge-warning  { background:#fef3c7; color:#92400e; }
.badge-critical { background:#fee2e2; color:#991b1b; }
.badge-info     { background:#dbeafe; color:#1e40af; }

/* Filter */
.filter-btn {
    padding:6px 14px; border-radius:10px; font-size:12px; font-weight:700;
    border:1.5px solid #e5e7eb; background:white; color:#6b7280;
    cursor:pointer; transition:all .2s ease;
}
.filter-btn:hover { border-color:#22c55e; color:#16a34a; }
.filter-btn.active { background:#22c55e; border-color:#22c55e; color:white; }
.filter-input {
    padding:6px 12px; border-radius:10px; font-size:12px; font-weight:600;
    border:1.5px solid #e5e7eb; background:white; color:#374151;
    outline:none; transition:border-color .2s;
}
.filter-input:focus { border-color:#22c55e; }
</style>
@endpush

@section('content')
<div class="p-4 md:p-6 space-y-5">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900">Riwayat Harian Kendaraan</h1>
            <p class="text-xs text-gray-400 mt-0.5">Track pergerakan kendaraan dalam periode tertentu</p>
        </div>
        <a href="{{ route('master.vehicles.index') }}" class="text-xs text-gray-500 hover:text-gray-800 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="card p-4">
        <form method="GET" action="{{ route('master.vehicles.history', $vehicle) }}" class="space-y-3">
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">Kendaraan</label>
                    <select name="vehicle_id" id="vehicle-select"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-green-400 bg-white">
                        @foreach($vehicles as $v)
                        <option value="{{ $v->id }}"
                            {{ $v->id == $vehicle->id ? 'selected' : '' }}
                            data-url="{{ route('master.vehicles.history', $v) }}">
                            {{ $v->name }} ({{ $v->license_plate }})
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Single Date Filter --}}
            <div class="flex flex-wrap gap-2 items-center">
                <input type="date" name="date"
                       value="{{ $date }}"
                       max="{{ now()->toDateString() }}"
                       class="filter-input" style="width:140px;">
                <button type="submit" class="filter-btn active">
                    Terapkan
                </button>
            </div>
        </form>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="stat-card">
            <div class="stat-val">{{ number_format($totalDistKm, 1) }} <span class="text-sm font-semibold text-gray-400">km</span></div>
            <div class="stat-lbl">Total Jarak</div>
        </div>
        <div class="stat-card">
            @php
                $h = intdiv(max(0, $movingSec), 3600);
                $m = intdiv(max(0, $movingSec) % 3600, 60);
            @endphp
            <div class="stat-val">{{ $h > 0 ? "{$h}j {$m}m" : "{$m}m" }}</div>
            <div class="stat-lbl">Waktu Bergerak</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">{{ number_format($maxSpeedKmh, 0) }} <span class="text-sm font-semibold text-gray-400">km/h</span></div>
            <div class="stat-lbl">Kecepatan Maks</div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Jumlah Trip</div>
            <div class="stat-val">{{ $trips->count() }} <span class="text-sm text-gray-500 font-normal">trip</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Jumlah Stop</div>
            <div class="stat-val">{{ count($stops) }} <span class="text-sm text-gray-500 font-normal">titik</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Waktu Berhenti</div>
            <div class="stat-val">
                @if($totalStopSec >= 3600)
                    {{ floor($totalStopSec / 3600) }}<span class="text-sm text-gray-500 font-normal">j</span>
                    {{ floor(($totalStopSec % 3600) / 60) }}<span class="text-sm text-gray-500 font-normal">m</span>
                @elseif($totalStopSec >= 60)
                    {{ floor($totalStopSec / 60) }}<span class="text-sm text-gray-500 font-normal">m</span>
                @else
                    {{ $totalStopSec }}<span class="text-sm text-gray-500 font-normal">d</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Map --}}
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm font-bold text-gray-700">
                Peta Pergerakan — {{ $filterLabel }}
            </span>
            <div class="flex flex-wrap items-center gap-3">
                {{-- Legend --}}
                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                    <span class="flex items-center gap-1">
                        <span class="inline-block w-4 h-1 rounded" style="background:#f97316;"></span>
                        Di luar trip
                    </span>
                    @foreach($tripsForMap as $i => $t)
                    @php
                        $tripColors = ['#3b82f6','#ef4444','#10b981','#8b5cf6','#f59e0b','#ec4899','#06b6d4','#84cc16','#6366f1','#14b8a6'];
                        $tc = $tripColors[$i % count($tripColors)];
                    @endphp
                    <span class="flex items-center gap-1">
                        <span class="inline-block w-4 h-1 rounded" style="background:{{ $tc }};"></span>
                        {{ $t['trip_code'] }}
                    </span>
                    @endforeach
                    <span class="flex items-center gap-1">
                        <span style="display:inline-block;width:12px;height:12px;background:#dc2626;border-radius:50%;border:2px solid white;box-shadow:0 0 0 3px #dc262640;flex-shrink:0;"></span>
                        Stop
                    </span>
                    <span class="flex items-center gap-1" style="color:#7c3aed;">
                        <span style="display:inline-block;width:16px;border-top:2px dashed #7c3aed;"></span>
                        Sinyal hilang
                    </span>
                </div>
            </div>
        </div>
        <div id="history-map"></div>
        <div id="history-gmap"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Trips that day --}}
        <div class="card">
            <div class="px-4 py-3 border-b border-gray-100">
                <span class="text-sm font-bold text-gray-700">Trip Periode Ini ({{ $trips->count() }})</span>
            </div>
            @if($trips->isEmpty())
            <div class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada trip pada periode ini</div>
            @else
            <ul class="divide-y divide-gray-50">
                @foreach($trips as $trip)
                <li class="px-4 py-3 flex items-center justify-between gap-2">
                    <div>
                        <a href="{{ route('trips.show', $trip) }}"
                           class="text-sm font-semibold text-green-700 hover:underline">{{ $trip->trip_code }}</a>
                        <div class="text-xs text-gray-500 mt-0.5">
                            {{ $trip->origin_name }} → {{ $trip->dest_name }}
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            {{ $trip->departed_at ? \Carbon\Carbon::parse($trip->departed_at)->format('H:i') : '—' }}
                            @if($trip->arrived_at)
                            → {{ \Carbon\Carbon::parse($trip->arrived_at)->format('H:i') }}
                            @endif
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold flex-shrink-0
                        {{ $trip->status === 'completed' ? 'bg-green-100 text-green-700' : ($trip->status === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500') }}">
                        {{ ['planned'=>'Planned','in_progress'=>'Berjalan','completed'=>'Selesai'][$trip->status] ?? $trip->status }}
                    </span>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

        {{-- Alerts that day --}}
        <div class="card">
            <div class="px-4 py-3 border-b border-gray-100">
                <span class="text-sm font-bold text-gray-700">Alert Periode Ini ({{ $dayAlerts->count() }})</span>
            </div>
            @if($dayAlerts->isEmpty())
            <div class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada alert pada periode ini</div>
            @else
            <ul class="divide-y divide-gray-50 max-h-64 overflow-y-auto">
                @foreach($dayAlerts as $alert)
                <li class="px-4 py-3 flex items-start gap-2.5">
                    <div class="mt-0.5 flex-shrink-0">
                        @if($alert->severity === 'critical') 
                        @elseif($alert->severity === 'warning') 
                        @else 
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-semibold text-gray-800">{{ $alert->title }}</span>
                            <span class="alert-badge {{ $alert->severity === 'critical' ? 'badge-critical' : ($alert->severity === 'warning' ? 'badge-warning' : 'badge-info') }}">
                                {{ strtoupper(str_replace('_', ' ', $alert->alert_type)) }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $alert->message }}</div>
                        <div class="text-xs text-gray-300 mt-0.5">{{ \Carbon\Carbon::parse($alert->triggered_at)->format('H:i:s') }}</div>
                    </div>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

    </div>
</div>

@push('scripts')
<script>
window.__historymap = {
    mapType:     "{{ $mapType }}",
    gmapsKey:    "{{ $googleMapsKey }}",
    segments:    @json($segments),
    signalGaps:  @json($signalGaps),
    stops:       @json($stops),
    trips:       @json($tripsForMap),
};

(function () {
    const CFG        = window.__historymap;
    const segments   = CFG.segments   ?? [];
    const signalGaps = CFG.signalGaps ?? [];
    const stops      = CFG.stops      ?? [];
    const trips      = CFG.trips      ?? [];

    // ── Palet warna per trip_id ──────────────────────────────────
    const TRIP_COLORS = ['#3b82f6','#ef4444','#10b981','#8b5cf6','#f59e0b','#ec4899','#06b6d4','#84cc16','#6366f1','#14b8a6'];
    const tripIdToColor = {};
    let colorIdx = 0;

    function trackColor(tripId) {
        if (tripId == null) return '#f97316'; // no trip = orange
        if (!tripIdToColor[tripId]) {
            tripIdToColor[tripId] = TRIP_COLORS[colorIdx % TRIP_COLORS.length];
            colorIdx++;
        }
        return tripIdToColor[tripId];
    }

    // Split a segment array into sub-segments by trip_id changes
    function splitByTrip(seg) {
        if (!seg.length) return [];
        const result = [];
        let cur = { tripId: seg[0].trip_id, pts: [seg[0]] };
        for (let i = 1; i < seg.length; i++) {
            const pt = seg[i];
            if (pt.trip_id !== cur.tripId) {
                result.push(cur);
                cur = { tripId: pt.trip_id, pts: [seg[i - 1], pt] }; // overlap 1 pt for continuity
            } else {
                cur.pts.push(pt);
            }
        }
        result.push(cur);
        return result;
    }

    // ── GAP icon ─────────────────────────────────────────────────
    function gapPopupContent(gap) {
        const mins = Math.floor(gap.duration_sec / 60);
        const secs = gap.duration_sec % 60;
        const dur  = mins > 0 ? `${mins}m ${secs}d` : `${secs}d`;
        const t1   = new Date(gap.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        const t2   = new Date(gap.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        return `<b style="color:#7c3aed"> Sinyal Hilang</b><br><small>${t1} — ${t2} (${dur})</small>`;
    }

    // ══════════════════════════════════════════════════════════════
    // OSM (Leaflet)
    // ══════════════════════════════════════════════════════════════
    function initOSM() {
        const el = document.getElementById('history-map');
        if (!el) return;

        const map = L.map('history-map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap', maxZoom: 19,
        }).addTo(map);

        const allLatLngs = [];

        segments.forEach(seg => {
            if (!seg.length) return;
            seg.forEach(p => allLatLngs.push([+p.latitude, +p.longitude]));

            splitByTrip(seg).forEach(sub => {
                if (sub.pts.length < 2) return;
                L.polyline(sub.pts.map(p => [+p.latitude, +p.longitude]), {
                    color:   trackColor(sub.tripId),
                    weight:  3.5,
                    opacity: 0.85,
                }).addTo(map);
            });
        });

        // Signal gaps — purple dashed
        const gapIcon = L.divIcon({
            html: '<div style="width:10px;height:10px;background:#7c3aed;border-radius:50%;border:2px solid white;"></div>',
            iconSize: [10, 10], iconAnchor: [5, 5], className: '',
        });
        signalGaps.forEach((gap, i) => {
            const nextSeg = segments[i + 1];
            if (!nextSeg || !nextSeg.length) return;
            const a = [+gap.lat, +gap.lng];
            const b = [+nextSeg[0].latitude, +nextSeg[0].longitude];
            L.polyline([a, b], { color: '#7c3aed', weight: 2.5, opacity: .8, dashArray: '8,6' }).addTo(map);
            L.marker([(a[0]+b[0])/2, (a[1]+b[1])/2], { icon: gapIcon, zIndexOffset: 300 })
             .addTo(map).bindPopup(gapPopupContent(gap));
        });

        // Stop markers
        stops.forEach(stop => {
            const stopIcon = L.divIcon({
                html: `<div style="width:16px;height:16px;background:#dc2626;border-radius:50%;
                            border:3px solid white;box-shadow:0 0 0 4px #dc262640;
                            display:flex;align-items:center;justify-content:center;">
                           <div style="width:5px;height:5px;background:white;border-radius:50%;"></div>
                       </div>`,
                iconSize: [16, 16], iconAnchor: [8, 8], className: '',
            });
            L.marker([stop.lat, stop.lng], { icon: stopIcon, zIndexOffset: 600 })
             .addTo(map)
             .bindPopup(`<div style="font-weight:700;color:#dc2626;"> Stop ${stop.duration_label}</div>
                         <div style="font-size:10px;color:#6b7280;margin-top:4px;">${stop.started_at} — ${stop.ended_at}</div>`);
        });

        // Start / end markers
        const firstSeg = segments.find(s => s.length);
        if (firstSeg) {
            const fp = firstSeg[0];
            L.circleMarker([+fp.latitude, +fp.longitude], {
                radius: 8, fillColor: '#16a34a', color: '#fff', weight: 2, fillOpacity: 1,
            }).addTo(map).bindPopup('<b style="color:#16a34a">▶ Mulai</b>');
        }
        const lastSeg = [...segments].reverse().find(s => s.length);
        if (lastSeg) {
            const lp = lastSeg[lastSeg.length - 1];
            L.circleMarker([+lp.latitude, +lp.longitude], {
                radius: 8, fillColor: '#ef4444', color: '#fff', weight: 2, fillOpacity: 1,
            }).addTo(map).bindPopup('<b style="color:#ef4444">■ Terakhir</b>');
        }

        // Trip origin / dest markers
        trips.forEach((t, idx) => {
            const color = TRIP_COLORS[idx % TRIP_COLORS.length];
            if (t.origin_lat) {
                L.circleMarker([+t.origin_lat, +t.origin_lng], {
                    radius: 7, fillColor: color, color: '#fff', weight: 1.5, fillOpacity: .9,
                }).addTo(map).bindPopup(`<b>Asal</b>: ${t.origin_name}<br><small>${t.trip_code}</small>`);
            }
            if (t.dest_lat) {
                L.circleMarker([+t.dest_lat, +t.dest_lng], {
                    radius: 7, fillColor: color, color: '#fff', weight: 1.5, fillOpacity: .9,
                }).addTo(map).bindPopup(`<b>Tujuan</b>: ${t.dest_name}<br><small>${t.trip_code}</small>`);
            }
        });

        if (allLatLngs.length) {
            map.fitBounds(L.latLngBounds(allLatLngs), { padding: [30, 30] });
        } else {
            map.setView([-6.9, 107.6], 10);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // Google Maps
    // ══════════════════════════════════════════════════════════════
    function initGMaps() {
        document.getElementById('history-map').style.display  = 'none';
        document.getElementById('history-gmap').style.display = 'block';

        if (!CFG.gmapsKey) { initOSM(); return; }
        if (window.google && window.google.maps) { createGMap(); return; }
        if (document.getElementById('gmaps-sdk-hist')) return;

        const s   = document.createElement('script');
        s.id      = 'gmaps-sdk-hist';
        s.src     = `https://maps.googleapis.com/maps/api/js?key=${CFG.gmapsKey}&callback=__createHistGMap&loading=async`;
        s.async   = true; s.defer = true;
        document.head.appendChild(s);
    }

    window.__createHistGMap = createGMap;

    function createGMap() {
        const allPts = segments.flatMap(s => s.map(p => ({ lat: +p.latitude, lng: +p.longitude })));
        const center = allPts.length ? allPts[0] : { lat: -6.9, lng: 107.6 };

        const gMap = new google.maps.Map(document.getElementById('history-gmap'), {
            center, zoom: 13, mapTypeId: 'roadmap',
            mapTypeControl: false, fullscreenControl: false, streetViewControl: false,
        });

        const bounds = new google.maps.LatLngBounds();

        segments.forEach(seg => {
            if (!seg.length) return;
            seg.forEach(p => bounds.extend({ lat: +p.latitude, lng: +p.longitude }));

            splitByTrip(seg).forEach(sub => {
                if (sub.pts.length < 2) return;
                new google.maps.Polyline({
                    path:          sub.pts.map(p => ({ lat: +p.latitude, lng: +p.longitude })),
                    strokeColor:   trackColor(sub.tripId),
                    strokeWeight:  4,
                    strokeOpacity: 0.85,
                    map:           gMap,
                });
            });
        });

        // Signal gaps — purple dashed
        const gapInfoWin = new google.maps.InfoWindow();
        signalGaps.forEach((gap, i) => {
            const nextSeg = segments[i + 1];
            if (!nextSeg || !nextSeg.length) return;
            const a = { lat: +gap.lat,               lng: +gap.lng };
            const b = { lat: +nextSeg[0].latitude,    lng: +nextSeg[0].longitude };
            new google.maps.Polyline({
                path: [a, b], strokeColor: '#7c3aed', strokeWeight: 3,
                strokeOpacity: .8, icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, scale: 4 },
                offset: '0', repeat: '16px' }], map: gMap,
            });
            const mid = { lat: (a.lat + b.lat) / 2, lng: (a.lng + b.lng) / 2 };
            const mk  = new google.maps.Marker({ position: mid, map: gMap,
                icon: { path: google.maps.SymbolPath.CIRCLE, scale: 6,
                    fillColor: '#7c3aed', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 } });
            mk.addListener('click', () => {
                gapInfoWin.setContent(gapPopupContent(gap));
                gapInfoWin.open(gMap, mk);
            });
        });

        // Stop markers
        const stopInfoWin = new google.maps.InfoWindow();
        stops.forEach(stop => {
            const mk = new google.maps.Marker({
                position: { lat: stop.lat, lng: stop.lng },
                map: gMap,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8,
                    fillColor: '#dc2626', fillOpacity: 1,
                    strokeColor: '#fff', strokeWeight: 2,
                },
                zIndex: 600,
            });
            mk.addListener('click', () => {
                stopInfoWin.setContent(
                    `<div style="font-weight:700;color:#dc2626;font-size:12px;"> Stop ${stop.duration_label}</div>
                     <div style="font-size:11px;color:#6b7280;margin-top:4px;">${stop.started_at} — ${stop.ended_at}</div>`
                );
                stopInfoWin.open(gMap, mk);
            });
        });

        // Start / end
        const firstSeg = segments.find(s => s.length);
        if (firstSeg) {
            const fp = firstSeg[0];
            new google.maps.Marker({ position: { lat: +fp.latitude, lng: +fp.longitude }, map: gMap,
                icon: { path: google.maps.SymbolPath.CIRCLE, scale: 9,
                    fillColor: '#16a34a', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
                title: 'Mulai' });
        }
        const lastSeg = [...segments].reverse().find(s => s.length);
        if (lastSeg) {
            const lp = lastSeg[lastSeg.length - 1];
            new google.maps.Marker({ position: { lat: +lp.latitude, lng: +lp.longitude }, map: gMap,
                icon: { path: google.maps.SymbolPath.CIRCLE, scale: 9,
                    fillColor: '#ef4444', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
                title: 'Terakhir' });
        }

        // Trip markers with unique colors
        const tripInfoWin = new google.maps.InfoWindow();
        trips.forEach((t, idx) => {
            const color = TRIP_COLORS[idx % TRIP_COLORS.length];
            [
                { lat: t.origin_lat, lng: t.origin_lng, label: `Asal: ${t.origin_name}`, c: color },
                { lat: t.dest_lat,   lng: t.dest_lng,   label: `Tujuan: ${t.dest_name}`, c: color },
            ].forEach(({ lat, lng, label, c }) => {
                if (!lat) return;
                const mk = new google.maps.Marker({ position: { lat: +lat, lng: +lng }, map: gMap,
                    icon: { path: google.maps.SymbolPath.CIRCLE, scale: 7,
                        fillColor: c, fillOpacity: .9, strokeColor: '#fff', strokeWeight: 2 } });
                mk.addListener('click', () => {
                    tripInfoWin.setContent(`<b>${label}</b><br><small>${t.trip_code}</small>`);
                    tripInfoWin.open(gMap, mk);
                });
            });
        });

        if (allPts.length) gMap.fitBounds(bounds);
    }

    // ── Bootstrap ─────────────────────────────────────────────────
    if (CFG.mapType === 'gmaps') {
        initGMaps();
    } else {
        initOSM();
    }

    // Vehicle dropdown redirect
    document.getElementById('vehicle-select')?.addEventListener('change', function () {
        const url  = this.options[this.selectedIndex].dataset.url;
        if (url) window.location = url;
    });
})();
</script>
@endpush
@endsection
