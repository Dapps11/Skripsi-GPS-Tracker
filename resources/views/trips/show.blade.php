@extends('layouts.app')
@section('title', 'Detail Trip — ' . $trip->trip_code)

@push('styles')
<style>
    #history-map  { height:560px; border-radius:14px; border:1.5px solid #e5e7eb; }
    #history-gmap { height:560px; border-radius:14px; display:none; }

    @media (max-width: 768px) {
        #history-map, #history-gmap { height: 280px; }
    }

    .stat-box   { background:#f9fafb; border-radius:12px; padding:14px; }
    .stat-label { font-size:9px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.08em; margin-bottom:4px; }
    .stat-value { font-size:20px; font-weight:800; color:#111827; line-height:1.2; }
    .stat-unit  { font-size:12px; font-weight:400; color:#9ca3af; }

    .legend-item { display:flex; align-items:center; gap:6px; font-size:11px; color:#374151; }
    .legend-line { width:28px; height:4px; border-radius:2px; flex-shrink:0; }
    .legend-dot  { width:12px; height:12px; border-radius:50%; border:2px solid white; flex-shrink:0; }

    /* Bubble chat untuk titik stop di peta */
    .stop-bubble-tooltip {
        background: white !important;
        border: 1.5px solid #dc2626 !important;
        border-radius: 10px !important;
        padding: 6px 10px !important;
        box-shadow: 0 2px 8px rgba(220,38,38,.25) !important;
        font-size: 11px !important;
        line-height: 1.4 !important;
    }
    .stop-bubble-tooltip::before {
        border-top-color: #dc2626 !important;
    }
    /* Bubble gap sinyal terputus */
    .gap-bubble-tooltip {
        border-color: #7c3aed !important;
        box-shadow: 0 2px 8px rgba(124,58,237,.25) !important;
    }
    .gap-bubble-tooltip::before {
        border-top-color: #7c3aed !important;
    }
</style>
@endpush

@section('content')
<div class="p-4 md:p-6 w-full max-w-[1600px] mx-auto">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-5">
        <a href="{{ route('trips.index') }}" class="hover:text-green-500 transition-colors">Trip Management</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">{{ $trip->trip_code }}</span>
    </div>

    {{-- Header --}}
    <div class="card p-5 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                    <span class="font-mono text-xs font-bold text-gray-400">{{ $trip->trip_code }}</span>
                    @php
                        $stMap = [
                            'planned'     => ['bg-blue-100 text-blue-700',   'Planned'],
                            'in_progress' => ['bg-green-100 text-green-700', 'In Progress'],
                            'completed'   => ['bg-gray-100 text-gray-600',   'Completed'],
                            'cancelled'   => ['bg-red-100 text-red-600',     'Cancelled'],
                        ];
                        [$stCls, $stLbl] = $stMap[$trip->status] ?? ['bg-gray-100 text-gray-600', $trip->status];
                    @endphp
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $stCls }}">{{ $stLbl }}</span>
                </div>
                <div class="text-lg md:text-xl font-extrabold text-gray-900 mb-0.5">
                    {{ $trip->vehicle->name ?? '—' }}
                    <span class="text-sm font-normal text-gray-400 ml-1">{{ $trip->vehicle->license_plate ?? '' }}</span>
                </div>
                <div class="text-sm text-gray-500">👤 {{ $trip->driver->full_name ?? '—' }}</div>
            </div>
            <div class="flex items-center gap-2 flex-wrap flex-shrink-0">
                @if($trip->status === 'in_progress')
                <a href="{{ route('livemap.show', $trip->vehicle_id) }}"
                   class="flex items-center gap-1.5 px-3 py-2 bg-green-500 hover:bg-green-600 text-white text-xs font-bold rounded-xl transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    Live Map
                </a>
                @endif
                <a href="{{ route('trips.index') }}"
                   class="px-3 py-2 border border-gray-200 text-xs font-semibold text-gray-600 rounded-xl hover:bg-gray-50 transition-all">
                    ← Kembali
                </a>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">

        {{-- Stats Panel --}}
        <div class="lg:col-span-1 space-y-3">

            {{-- Rute --}}
            <div class="card p-4">
                <div class="flex gap-3">
                    <div class="flex flex-col items-center pt-1 flex-shrink-0">
                        <div class="w-3 h-3 bg-green-500 rounded-full border-2 border-white shadow"></div>
                        <div class="w-px flex-1 bg-gray-200 my-1.5" style="min-height:28px;"></div>
                        <div class="w-3 h-3 bg-red-500 rounded-full border-2 border-white shadow"></div>
                    </div>
                    <div class="flex-1 space-y-3 min-w-0">
                        <div>
                            <div class="text-[9px] font-bold text-green-600 uppercase tracking-wider mb-0.5">Start</div>
                            <div class="text-sm font-semibold text-gray-900 leading-tight">{{ $trip->origin_name }}</div>
                            @if($trip->departed_at)
                            <div class="text-xs text-gray-400 mt-0.5">
                                {{ \Carbon\Carbon::parse($trip->departed_at)->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                            </div>
                            @else
                            <div class="text-xs text-gray-400 italic">Belum berangkat</div>
                            @endif
                        </div>
                        <div>
                            <div class="text-[9px] font-bold text-red-500 uppercase tracking-wider mb-0.5">Tujuan</div>
                            <div class="text-sm font-semibold text-gray-900 leading-tight">{{ $trip->dest_name }}</div>
                            @if($trip->arrived_at)
                            <div class="text-xs text-gray-400 mt-0.5">
                                Tiba: {{ \Carbon\Carbon::parse($trip->arrived_at)->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                            </div>
                            @elseif($trip->estimated_arrival_at)
                            <div class="text-xs text-gray-400 mt-0.5">
                                Est: {{ \Carbon\Carbon::parse($trip->estimated_arrival_at)->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stats Grid --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="stat-box">
                    <div class="stat-label">Jarak Tempuh</div>
                    <div class="stat-value">
                        @if($trip->total_distance_km)
                            {{ number_format($trip->total_distance_km, 1) }}
                            <span class="stat-unit">km</span>
                        @else
                            <span style="font-size:14px;color:#9ca3af;">Menghitung...</span>
                        @endif
                    </div>
                </div>

                <div class="stat-box">
                    <div class="stat-label">Durasi</div>
                    <div class="stat-value">
                        @if($trip->departed_at && $trip->arrived_at)
                            @php
                                $durMenit = (int) \Carbon\Carbon::parse($trip->departed_at)
                                                                ->diffInMinutes($trip->arrived_at);
                                $durJam   = (int) floor($durMenit / 60);
                                $durSisa  = $durMenit % 60;
                            @endphp
                            @if($durJam > 0)
                                {{ $durJam }}<span class="stat-unit">j</span> {{ $durSisa }}<span class="stat-unit">mnt</span>
                            @else
                                {{ $durMenit }}<span class="stat-unit"> mnt</span>
                            @endif
                        @elseif($trip->departed_at && $trip->status === 'in_progress')
                            @php $durMenit = (int) \Carbon\Carbon::parse($trip->departed_at)->diffInMinutes(now()); @endphp
                            {{ $durMenit }}<span class="stat-unit"> mnt</span>
                        @else
                            <span style="font-size:14px;color:#9ca3af;">—</span>
                        @endif
                    </div>
                </div>

                {{-- ETA Awal --}}
                @if($trip->origin_lat && $trip->dest_lat)

                <div class="stat-box col-span-2">
                    <div class="stat-label">ETA Awal</div>

                    {{-- Haversine --}}
                    <div class="stat-value" style="margin-bottom:6px;">
                        {{ $etaHaversine ?? '—' }}
                        <span class="stat-unit">mnt</span>
                    </div>
                    <div style="font-size:10px;color:#16a34a;margin-bottom:8px;">
                        Haversine (estimasi manual)
                    </div>

                    {{-- API --}}
                    <div class="stat-value">
                        <span id="eta-api-value">—</span>
                        <span class="stat-unit">mnt</span>
                    </div>
                    <div style="font-size:10px;color:#2563eb;">
                        <span id="eta-api-label">
                            {{ $mapType === 'gmaps' ? 'Google Maps' : 'OSRM' }}
                        </span> •
                        <span id="eta-api-dist">—</span> km
                    </div>
                </div>

                @endif

                <div class="stat-box">
                    <div class="stat-label">GPS Points</div>
                    <div class="stat-value">
                        {{ $gpsPoints->count() }}
                        <span class="stat-unit">titik</span>
                    </div>
                </div>

                <div class="stat-box">
                    <div class="stat-label">Jumlah Stop</div>
                    <div class="stat-value">
                        {{ count($stops) }}
                        <span class="stat-unit">kali</span>
                    </div>
                    @if(count($stops) > 0)
                    @php
                        $totalStopSec = collect($stops)->sum('duration_seconds');
                        $tsMin = intdiv($totalStopSec, 60);
                        $tsSec = $totalStopSec % 60;
                        $totalStopLabel = $tsMin >= 60
                            ? floor($tsMin / 60) . 'j ' . ($tsMin % 60) . 'm ' . $tsSec . 'd'
                            : ($tsMin > 0 ? $tsMin . 'm ' . $tsSec . 'd' : $tsSec . 'd');
                    @endphp
                    <div style="font-size:10px;color:#9ca3af;margin-top:2px;">total {{ $totalStopLabel }} berhenti</div>
                    @endif
                </div>

                @if(count($signalGaps) > 0)
                <div class="stat-box" style="border:1px solid #ede9fe;background:#faf5ff;">
                    <div class="stat-label" style="color:#7c3aed;">📡 Sinyal Terputus</div>
                    <div class="stat-value" style="color:#7c3aed;">
                        {{ count($signalGaps) }}
                        <span class="stat-unit">kali</span>
                    </div>
                    @php
                        $totalGapSec = collect($signalGaps)->sum('duration_sec');
                        $gMin = intdiv($totalGapSec, 60);
                        $gSec = $totalGapSec % 60;
                        $totalGapLabel = $gMin > 0 ? "{$gMin}m {$gSec}d" : "{$gSec}d";
                    @endphp
                    <div style="font-size:10px;color:#9ca3af;margin-top:2px;">total {{ $totalGapLabel }} tanpa sinyal</div>
                </div>
                @endif

                <div class="stat-box">
                    <div class="stat-label">Device</div>
                    <div style="font-size:11px;font-weight:700;color:#111827;margin-top:4px;word-break:break-all;">
                        {{ $trip->device->device_id ?? '—' }}
                    </div>
                </div>

                @if($gpsPoints->count() > 0)
                @php
                    $speeds = $gpsPoints->pluck('speed_kmh')->filter(fn($s) => $s > 0);
                    $maxSpd = $speeds->count() ? (int) round($speeds->max()) : 0;
                    $avgSpd = $speeds->count() ? (int) round($speeds->avg()) : 0;
                @endphp
                <div class="stat-box">
                    <div class="stat-label">Kec. Maks</div>
                    <div class="stat-value">{{ $maxSpd }}<span class="stat-unit"> km/h</span></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Kec. Rata</div>
                    <div class="stat-value">{{ $avgSpd }}<span class="stat-unit"> km/h</span></div>
                </div>
                @endif

                {{-- Monitoring kantuk --}}
                @if($monitoringEvents->count() > 0)
                @php
                    $alarmCount = $monitoringEvents->where('is_alarm', 1)->count();
                    $drowsyCount = $monitoringEvents->whereIn('event_type', ['drowsy', 'drowsy_warning'])->count();
                @endphp
                <div class="stat-box col-span-2" style="background:#fff7ed;border:1px solid #fed7aa;">
                    <div class="stat-label" style="color:#c2410c;">⚠️ Deteksi Kantuk</div>
                    <div class="flex items-end gap-3 mt-1">
                        <div>
                            <div class="stat-value" style="color:#dc2626;">{{ $alarmCount }}</div>
                            <div style="font-size:9px;color:#9ca3af;font-weight:600;text-transform:uppercase;">Alarm</div>
                        </div>
                        <div style="width:1px;height:32px;background:#fed7aa;flex-shrink:0;"></div>
                        <div>
                            <div class="stat-value" style="color:#f97316;">{{ $drowsyCount }}</div>
                            <div style="font-size:9px;color:#9ca3af;font-weight:600;text-transform:uppercase;">Drowsy</div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Legend --}}
            @if($gpsPoints->count() >= 2)
            <div class="card p-3 space-y-2">
                <div class="text-xs font-bold text-gray-500 mb-1">Keterangan Peta</div>
                <div class="legend-item">
                    <div class="legend-line" style="background:#4f46e5;"></div>
                    <span style="color:#4f46e5;font-weight:600;">Rute Jalan</span>
                </div>
                <div class="legend-item">
                    <div class="legend-line" style="background:#f97316;"></div>
                    <span style="color:#f97316;font-weight:600;">Riwayat GPS</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#22c55e;box-shadow:0 0 0 2px #22c55e55;"></div>
                    <span>Titik Awal</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#ef4444;box-shadow:0 0 0 2px #ef444455;"></div>
                    <span>Titik Tujuan</span>
                </div>
                @if(count($stops) > 0)
                <div class="legend-item">
                    <div class="legend-dot" style="background:#dc2626;box-shadow:0 0 0 2px #dc262655;"></div>
                    <span>Titik Berhenti ({{ count($stops) }})</span>
                </div>
                @endif
                @if(count($signalGaps) > 0)
                <div class="legend-item">
                    <div class="legend-dot" style="background:#7c3aed;box-shadow:0 0 0 2px #7c3aed55;"></div>
                    <span style="color:#7c3aed;font-weight:600;">Sinyal Terputus ({{ count($signalGaps) }})</span>
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Map --}}
        <div class="lg:col-span-3">
            <div id="history-map"></div>
            <div id="history-gmap"></div>

            @if($gpsPoints->count() === 0)
            <div class="mt-3 p-4 bg-yellow-50 border border-yellow-200 rounded-xl text-sm text-yellow-700 text-center">
                ⚠️ Belum ada data GPS untuk trip ini.
            </div>
            @endif
        </div>
    </div>

    {{-- GPS Timeline --}}
    @if($gpsPoints->count() > 0)
    <div class="card p-5">
        <h3 class="text-sm font-bold text-gray-900 mb-3">
            📍 GPS Track Timeline
            <span class="text-xs font-normal text-gray-400 ml-2">({{ $gpsPoints->count() }} titik total)</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left font-bold text-gray-400 uppercase pb-2 pr-4">#</th>
                        <th class="text-left font-bold text-gray-400 uppercase pb-2 pr-4">Waktu (WIB)</th>
                        <th class="text-left font-bold text-gray-400 uppercase pb-2 pr-4">Koordinat</th>
                        <th class="text-left font-bold text-gray-400 uppercase pb-2">Kecepatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @php
                        $total   = $gpsPoints->count();
                        $indices = collect();
                        if ($total <= 20) {
                            $indices = collect(range(0, $total - 1));
                        } else {
                            $indices->push(0);
                            for ($i = 1; $i <= 18; $i++) {
                                $indices->push((int) round($i * ($total - 1) / 19));
                            }
                            $indices->push($total - 1);
                            $indices = $indices->unique()->values();
                        }
                    @endphp

                    @foreach($indices as $i => $idx)
                    @php $pt = $gpsPoints[$idx]; @endphp
                    <tr class="{{ $idx === 0 ? 'bg-green-50' : ($idx === $total-1 ? 'bg-red-50' : 'hover:bg-gray-50') }} transition-colors">
                        <td class="py-2 pr-4 text-gray-400">
                            @if($idx === 0)
                                <span class="px-1.5 py-0.5 bg-green-100 text-green-700 rounded text-[10px] font-bold">START</span>
                            @elseif($idx === $total-1)
                                <span class="px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-[10px] font-bold">END</span>
                            @else
                                {{ $idx + 1 }}
                            @endif
                        </td>
                        <td class="py-2 pr-4 font-mono text-gray-600">
                            {{ $pt->gps_timestamp_wib->format('H:i:s') }}
                        </td>
                        <td class="py-2 pr-4 font-mono text-gray-500">
                            {{ number_format($pt->latitude, 7) }}, {{ number_format($pt->longitude, 7) }}
                        </td>
                        <td class="py-2">
                            @php $spd = (int) round($pt->speed_kmh); @endphp
                            <span class="{{ $spd > 2 ? 'text-green-600 font-semibold' : 'text-gray-400' }}">
                                {{ $spd }} km/h
                            </span>
                        </td>
                    </tr>
                    @endforeach

                    @if($total > 20)
                    <tr>
                        <td colspan="4" class="py-2 text-center text-gray-400 italic text-[11px]">
                            Menampilkan 20 dari {{ $total }} titik GPS
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Grafik Deteksi Kantuk --}}
    @if($monitoringEvents->count() > 0)
    @php
        $alarmEvents  = $monitoringEvents->where('is_alarm', 1);
        $drowsyEvents = $monitoringEvents->whereIn('event_type', ['drowsy', 'drowsy_warning']);
        $normalEvents = $monitoringEvents->where('event_type', 'normal');

        // Hitung ringkasan reasons
        $allReasons = $monitoringEvents->pluck('reasons')->filter()->flatMap(fn($r) => explode(', ', $r));
        $reasonCounts = $allReasons->countBy()->sortDesc();
    @endphp
    <div class="card p-5 mt-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-gray-900">
                😴 Grafik Deteksi Kantuk
                <span class="text-xs font-normal text-gray-400 ml-2">({{ $monitoringEvents->count() }} event)</span>
            </h3>
            {{-- Legend --}}
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div> Alarm ({{ $alarmEvents->count() }})
                </div>
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded-full bg-orange-400"></div> Drowsy ({{ $drowsyEvents->count() }})
                </div>
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded-full bg-green-400"></div> Normal ({{ $normalEvents->count() }})
                </div>
            </div>
        </div>

        {{-- Ringkasan reasons --}}
        @if($reasonCounts->count() > 0)
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach($reasonCounts as $reason => $count)
            <span class="px-2.5 py-1 text-xs font-semibold rounded-full
                {{ str_contains($reason, 'PERCLOS') ? 'bg-red-100 text-red-700' :
                   (str_contains($reason, 'YAWN') ? 'bg-orange-100 text-orange-700' :
                   (str_contains($reason, 'MICROSLEEP') ? 'bg-yellow-100 text-yellow-700' :
                   'bg-gray-100 text-gray-600')) }}">
                {{ $reason }} <span class="opacity-60">×{{ $count }}</span>
            </span>
            @endforeach
        </div>
        @endif

        {{-- Canvas grafik --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div>
                <div class="text-xs font-bold text-gray-500 mb-2">PERCLOS & Event Type (over time)</div>
                <canvas id="chart-perclos" height="160"></canvas>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-500 mb-2">EAR & MAR (over time)</div>
                <canvas id="chart-ear-mar" height="160"></canvas>
            </div>
        </div>

        {{-- DEBUG — hapus setelah dicek --}}
<div class="p-3 bg-gray-100 text-xs font-mono mt-4">
    Monitoring events: {{ $monitoringEvents->count() }}<br>
    Trip departed_at: {{ $trip->departed_at }}<br>
    Trip arrived_at: {{ $trip->arrived_at }}<br>
    Trip vehicle_id: {{ $trip->vehicle_id }}
</div>

        {{-- Tabel event alarm --}}
        @if($alarmEvents->count() > 0)
        <div class="mt-4">
            <div class="text-xs font-bold text-gray-500 mb-2">🚨 Daftar Event Alarm</div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left font-bold text-gray-400 uppercase pb-2 pr-4">Waktu</th>
                            <th class="text-left font-bold text-gray-400 uppercase pb-2 pr-4">Alasan</th>
                            <th class="text-left font-bold text-gray-400 uppercase pb-2 pr-4">PERCLOS</th>
                            <th class="text-left font-bold text-gray-400 uppercase pb-2 pr-4">EAR</th>
                            <th class="text-left font-bold text-gray-400 uppercase pb-2">MAR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($alarmEvents as $ev)
                        <tr class="bg-red-50">
                            <td class="py-2 pr-4 font-mono text-gray-700">
                                {{ \Carbon\Carbon::parse($ev->event_timestamp)->setTimezone('Asia/Jakarta')->format('H:i:s') }}
                            </td>
                            <td class="py-2 pr-4">
                                @foreach(explode(', ', $ev->reasons ?? '') as $r)
                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold mr-1
                                    {{ str_contains($r, 'PERCLOS') ? 'bg-red-200 text-red-800' :
                                       (str_contains($r, 'YAWN') ? 'bg-orange-200 text-orange-800' :
                                       'bg-yellow-200 text-yellow-800') }}">{{ $r }}</span>
                                @endforeach
                            </td>
                            <td class="py-2 pr-4 font-mono {{ $ev->perclos_value > 0.4 ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                                {{ $ev->perclos_value !== null ? number_format($ev->perclos_value, 3) : '—' }}
                            </td>
                            <td class="py-2 pr-4 font-mono {{ $ev->ear_value < 0.2 ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                                {{ $ev->ear_value !== null ? number_format($ev->ear_value, 3) : '—' }}
                            </td>
                            <td class="py-2 font-mono {{ $ev->mar_value > 0.8 ? 'text-orange-600 font-bold' : 'text-gray-600' }}">
                                {{ $ev->mar_value !== null ? number_format($ev->mar_value, 3) : '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
// ── Data server → trips-show.js ──────────────────────────────────
window.__tripshow = {
    mapType:          "{{ $mapType }}",
    gmapsKey:         "{{ $googleMapsKey }}",
    gpsPoints:        @json($gpsPointsForMap),
    gpsPointsRaw:     @json($gpsPoints),
    trip:             @json($trip),
    stopEvents:       @json($stops),
    gpsSegments:      @json($gpsSegments),
    signalGaps:       @json($signalGaps),
    monitoringEvents: @json($monitoringForChart),
};
</script>
<script src="{{ asset('js/trips-show.js') }}"></script>
@if($monitoringEvents->count() > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/trips-show-chart.js') }}"></script>
@endif
@endpush