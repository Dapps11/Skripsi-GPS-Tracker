@extends('layouts.app')
@section('title', 'Riwayat Harian — ' . $vehicle->name)

@push('styles')
<style>
#history-map { height: 460px; border-radius: 0.75rem; }
.stat-card   { background:#fff; border:1.5px solid #f1f5f9; border-radius:1rem; padding:1rem 1.25rem; }
.stat-val    { font-size:1.5rem; font-weight:800; color:#111827; }
.stat-lbl    { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#9ca3af; margin-top:.15rem; }
.alert-badge { display:inline-block; padding:1px 8px; border-radius:999px; font-size:.65rem; font-weight:700; }
.badge-warning  { background:#fef3c7; color:#92400e; }
.badge-critical { background:#fee2e2; color:#991b1b; }
.badge-info     { background:#dbeafe; color:#1e40af; }
</style>
@endpush

@section('content')

<div class="p-4 md:p-6 space-y-5">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900">Riwayat Harian Kendaraan</h1>
            <p class="text-xs text-gray-400 mt-0.5">Track pergerakan kendaraan dalam satu hari</p>
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
        <form method="GET" action="{{ route('master.vehicles.history', $vehicle) }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Kendaraan</label>
                <select name="vehicle_id" onchange="this.form.submit()"
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
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Tanggal</label>
                <input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-green-400">
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-lg transition">
                Tampilkan
            </button>
        </form>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="stat-card">
            <div class="stat-val">{{ number_format($totalDistKm, 1) }} <span class="text-sm font-semibold text-gray-400">km</span></div>
            <div class="stat-lbl">Total Jarak</div>
        </div>
        <div class="stat-card">
            @php
                $h = intdiv($movingSec, 3600);
                $m = intdiv($movingSec % 3600, 60);
            @endphp
            <div class="stat-val">{{ $h > 0 ? "{$h}j {$m}m" : "{$m}m" }}</div>
            <div class="stat-lbl">Waktu Bergerak</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">{{ number_format($maxSpeedKmh, 0) }} <span class="text-sm font-semibold text-gray-400">km/h</span></div>
            <div class="stat-lbl">Kecepatan Maks</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">{{ $count }}</div>
            <div class="stat-lbl">Data GPS</div>
        </div>
    </div>

    {{-- Map --}}
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-sm font-bold text-gray-700">Peta Pergerakan — {{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}</span>
            <div class="flex items-center gap-3 text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="inline-block w-4 h-1 bg-blue-500 rounded"></span> Track GPS</span>
                <span class="flex items-center gap-1"><span class="inline-block w-4 h-1 bg-green-500 rounded"></span> Dalam Trip</span>
                <span class="flex items-center gap-1" style="color:#7c3aed"><span class="inline-block w-4 h-0.5 border-t-2 border-dashed border-purple-600" style="width:16px"></span> Sinyal Hilang</span>
            </div>
        </div>
        <div id="history-map" class="rounded-b-2xl"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Trips that day --}}
        <div class="card">
            <div class="px-4 py-3 border-b border-gray-100">
                <span class="text-sm font-bold text-gray-700">Trip Hari Ini ({{ $trips->count() }})</span>
            </div>
            @if($trips->isEmpty())
            <div class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada trip pada tanggal ini</div>
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
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold
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
                <span class="text-sm font-bold text-gray-700">Alert Hari Ini ({{ $dayAlerts->count() }})</span>
            </div>
            @if($dayAlerts->isEmpty())
            <div class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada alert pada tanggal ini</div>
            @else
            <ul class="divide-y divide-gray-50 max-h-64 overflow-y-auto">
                @foreach($dayAlerts as $alert)
                <li class="px-4 py-3 flex items-start gap-2.5">
                    <div class="mt-0.5">
                        @if($alert->severity === 'critical')
                            <span class="text-red-500 text-base">🚨</span>
                        @elseif($alert->severity === 'warning')
                            <span class="text-yellow-500 text-base">⚠️</span>
                        @else
                            <span class="text-blue-400 text-base">ℹ️</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-semibold text-gray-800">{{ $alert->title }}</span>
                            <span class="alert-badge {{ $alert->severity === 'critical' ? 'badge-critical' : ($alert->severity === 'warning' ? 'badge-warning' : 'badge-info') }}">
                                {{ strtoupper($alert->alert_type) }}
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
(function () {
    const segments   = @json($segments);
    const signalGaps = @json($signalGaps);
    const trips      = @json($tripsForMap);

    const map = L.map('history-map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    const allLatLngs = [];

    // Draw GPS track segments
    segments.forEach(seg => {
        if (!seg.length) return;
        const latlngs = seg.map(p => [+p.latitude, +p.longitude]);
        allLatLngs.push(...latlngs);
        L.polyline(latlngs, { color: '#3b82f6', weight: 3, opacity: 0.75 }).addTo(map);
    });

    // Draw signal gaps (purple dashed)
    const gapDotIcon = L.divIcon({
        html: '<div style="width:12px;height:12px;background:#7c3aed;border-radius:50%;border:2px solid white;box-shadow:0 0 0 2px #7c3aed55;"></div>',
        iconSize: [12,12], iconAnchor: [6,6], className: ''
    });
    signalGaps.forEach((gap, i) => {
        const nextSeg = segments[i + 1];
        if (!nextSeg || !nextSeg.length) return;
        const startPt = [+gap.lat, +gap.lng];
        const endPt   = [+nextSeg[0].latitude, +nextSeg[0].longitude];
        const midPt   = [(startPt[0]+endPt[0])/2, (startPt[1]+endPt[1])/2];
        L.polyline([startPt, endPt], {
            color:'#7c3aed', weight:2.5, opacity:.8, dashArray:'8,6'
        }).addTo(map);
        const mins = Math.floor(gap.duration_sec / 60);
        const secs = gap.duration_sec % 60;
        const durLabel = mins > 0 ? `${mins}m ${secs}d` : `${secs}d`;
        const t1 = new Date(gap.start_at).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
        const t2 = new Date(gap.end_at).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
        L.marker(midPt, { icon: gapDotIcon, zIndexOffset: 300 }).addTo(map)
         .bindPopup(`<b style="color:#7c3aed">📡 Sinyal Hilang</b><br><small>${t1} — ${t2} (${durLabel})</small>`);
    });

    // Draw first & last point markers
    if (segments.length && segments[0].length) {
        const fp = segments[0][0];
        L.circleMarker([+fp.latitude, +fp.longitude], {
            radius: 8, fillColor: '#16a34a', color: '#fff', weight: 2, fillOpacity: 1
        }).addTo(map).bindPopup('<b style="color:#16a34a">Mulai</b>');
    }
    const lastSeg = [...segments].reverse().find(s => s.length);
    if (lastSeg) {
        const lp = lastSeg[lastSeg.length - 1];
        L.circleMarker([+lp.latitude, +lp.longitude], {
            radius: 8, fillColor: '#ef4444', color: '#fff', weight: 2, fillOpacity: 1
        }).addTo(map).bindPopup('<b style="color:#ef4444">Terakhir</b>');
    }

    // Draw trip origin/destination markers
    trips.forEach(trip => {
        if (trip.origin_lat && trip.origin_lng) {
            L.circleMarker([+trip.origin_lat, +trip.origin_lng], {
                radius: 6, fillColor: '#22c55e', color: '#16a34a', weight: 1.5, fillOpacity: 0.9
            }).addTo(map)
             .bindPopup(`<b>Asal</b>: ${trip.origin_name}<br><small>${trip.trip_code}</small>`);
        }
        if (trip.dest_lat && trip.dest_lng) {
            L.circleMarker([+trip.dest_lat, +trip.dest_lng], {
                radius: 6, fillColor: '#f97316', color: '#ea580c', weight: 1.5, fillOpacity: 0.9
            }).addTo(map)
             .bindPopup(`<b>Tujuan</b>: ${trip.dest_name}<br><small>${trip.trip_code}</small>`);
        }
    });

    // Fit bounds
    if (allLatLngs.length) {
        map.fitBounds(L.latLngBounds(allLatLngs), { padding: [30, 30] });
    } else {
        map.setView([-6.9, 107.6], 10); // default: Jawa Barat
    }

    // Vehicle dropdown redirect
    const sel = document.querySelector('select[name="vehicle_id"]');
    if (sel) {
        sel.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            const url = opt.dataset.url;
            const date = document.querySelector('input[name="date"]').value;
            if (url) window.location = url + '?date=' + date;
        });
    }
})();
</script>
@endpush
@endsection
