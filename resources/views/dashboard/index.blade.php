@extends('layouts.app')
@section('title', 'Dashboard — Greenfields')

@push('styles')
<style>
    .stat-card { background:white; border-radius:1rem; border:1.5px solid #f1f5f9; box-shadow:0 1px 4px rgba(0,0,0,.04); padding:1.25rem; }
    .stat-label { font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px; }
    .stat-value { font-size:28px; font-weight:800; color:#111827; line-height:1; }
    .stat-unit  { font-size:13px; font-weight:400; color:#9ca3af; margin-left:2px; }
    .stat-delta { font-size:11px; font-weight:600; margin-top:4px; }
    .delta-up   { color:#16a34a; }
    .delta-down { color:#dc2626; }
    .section-label { font-size:13px; font-weight:700; color:#111827; margin-bottom:.875rem; display:flex; align-items:center; gap:6px; }
    .card { background:white; border-radius:1rem; border:1.5px solid #f1f5f9; box-shadow:0 1px 4px rgba(0,0,0,.04); }
    .row-item { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid #f9fafb; }
    .row-item:last-child { border-bottom:none; padding-bottom:0; }
    .badge-status { padding:2px 8px; border-radius:99px; font-size:10px; font-weight:700; }

    /* Filter bar */
    .filter-bar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .filter-btn {
        padding:6px 16px; border-radius:10px; font-size:12px; font-weight:700;
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
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl md:text-2xl font-extrabold text-gray-900">Dashboard</h1>
            <p class="text-xs text-gray-400 mt-0.5">{{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</p>
        </div>
        <a href="{{ route('livemap.index') }}"
           class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md">
            <span style="width:7px;height:7px;background:white;border-radius:50%;animation:pulse 2s infinite;display:inline-block;"></span>
            Live Map
        </a>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="card p-4">
        <form method="GET" action="{{ route('dashboard') }}" id="dashboard-filter-form">
            <div class="filter-bar">
                <button type="submit" name="filter" value="week"
                        class="filter-btn {{ $filter === 'week' ? 'active' : '' }}">
                     Minggu Ini
                </button>
                <button type="submit" name="filter" value="month"
                        class="filter-btn {{ $filter === 'month' ? 'active' : '' }}">
                     Bulan Ini
                </button>

                <div style="display:flex;align-items:center;gap:6px;margin-left:4px;">
                    <span style="font-size:11px;font-weight:700;color:#9ca3af;">RANGE:</span>
                    <input type="date" name="start_date"
                           value="{{ $filter === 'custom' ? $startDate->format('Y-m-d') : '' }}"
                           class="filter-input" style="width:140px;"
                           onchange="document.getElementById('filter-custom-btn').click()">
                    <span style="font-size:11px;color:#9ca3af;">s/d</span>
                    <input type="date" name="end_date"
                           value="{{ $filter === 'custom' ? $endDate->format('Y-m-d') : '' }}"
                           max="{{ now()->toDateString() }}"
                           class="filter-input" style="width:140px;"
                           onchange="document.getElementById('filter-custom-btn').click()">
                    <button type="submit" name="filter" value="custom" id="filter-custom-btn"
                            class="filter-btn {{ $filter === 'custom' ? 'active' : '' }}">
                         Terapkan
                    </button>
                </div>

                @if($filter !== 'week')
                <a href="{{ route('dashboard') }}" style="font-size:11px;color:#9ca3af;text-decoration:none;margin-left:4px;"
                   onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='#9ca3af'"> Reset</a>
                @endif
            </div>
        </form>
        <div style="margin-top:8px;font-size:11px;color:#9ca3af;font-weight:600;">
             Menampilkan data: <span style="color:#111827;">{{ $filterLabel }}</span>
        </div>
    </div>

    {{-- ── Row 1: Fleet status + trip stats ── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">

        {{-- Moving --}}
        <div class="stat-card flex items-center gap-3">
            <div style="width:44px;height:44px;background:#dcfce7;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <div class="stat-label">Bergerak</div>
                <div class="stat-value" id="cnt-moving">{{ $fleetSummary->moving ?? 0 }}</div>
                <div class="stat-delta" style="color:#9ca3af;">kendaraan</div>
            </div>
        </div>

        {{-- Idle --}}
        <div class="stat-card flex items-center gap-3">
            <div style="width:44px;height:44px;background:#ffedd5;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke="#c2410c" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="stat-label">Idle</div>
                <div class="stat-value" id="cnt-idle">{{ $fleetSummary->idle ?? 0 }}</div>
                <div class="stat-delta" style="color:#9ca3af;">kendaraan</div>
            </div>
        </div>

        {{-- Offline --}}
        <div class="stat-card flex items-center gap-3">
            <div style="width:44px;height:44px;background:#fee2e2;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke="#b91c1c" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
            <div>
                <div class="stat-label">Offline</div>
                <div class="stat-value" id="cnt-offline">{{ $fleetSummary->offline ?? 0 }}</div>
                <div class="stat-delta" style="color:#9ca3af;">kendaraan</div>
            </div>
        </div>

        {{-- Trip in progress --}}
        <div class="stat-card flex items-center gap-3">
            <div style="width:44px;height:44px;background:#eff6ff;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke="#2563eb" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
            </div>
            <div>
                <div class="stat-label">Trip Aktif</div>
                <div class="stat-value">{{ $tripStats['in_progress'] }}</div>
                <div class="stat-delta" style="color:#9ca3af;">sedang berjalan</div>
            </div>
        </div>
    </div>

    {{-- ── Row 2: Periode metrics ── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
        @php
            $distDelta = $prevDistanceKm > 0
                ? round((($totalDistanceKm - $prevDistanceKm) / $prevDistanceKm) * 100)
                : null;
        @endphp

        <div class="stat-card">
            <div class="stat-label">Trip Selesai — {{ $filterLabel }}</div>
            <div class="stat-value">{{ $tripStats['completed'] }}<span class="stat-unit">trip</span></div>
            <div class="stat-delta" style="color:#9ca3af;">dari {{ $tripStats['total'] }} total</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Jarak Tempuh — {{ $filterLabel }}</div>
            <div class="stat-value">{{ number_format($totalDistanceKm, 0) }}<span class="stat-unit">km</span></div>
            @if($distDelta !== null)
            <div class="stat-delta {{ $distDelta >= 0 ? 'delta-up' : 'delta-down' }}">
                {{ $distDelta >= 0 ? '↑' : '↓' }} {{ abs($distDelta) }}% vs periode lalu
            </div>
            @endif
        </div>

        <div class="stat-card">
            <div class="stat-label">Event Kantuk — {{ $filterLabel }}</div>
            <div class="stat-value" style="{{ $drowsyStatsMonth['alarms'] > 0 ? 'color:#dc2626;' : '' }}">
                {{ $drowsyStatsMonth['total'] }}<span class="stat-unit">event</span>
            </div>
            <div class="stat-delta {{ $drowsyStatsMonth['alarms'] > 0 ? 'delta-down' : '' }}" style="{{ $drowsyStatsMonth['alarms'] > 0 ? '' : 'color:#9ca3af;' }}">
                {{ $drowsyStatsMonth['alarms'] }} alarm aktif
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Trip Planned</div>
            <div class="stat-value">{{ $tripStats['planned'] }}<span class="stat-unit">trip</span></div>
            <div class="stat-delta" style="color:#9ca3af;">belum berangkat</div>
        </div>
    </div>

    {{-- ── Row 3: Charts ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Chart: Trip & Jarak --}}
        <div class="card p-4">
            <div class="section-label">
                <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Trip Selesai — {{ $filterLabel }}
            </div>
            <canvas id="chart-trip-daily" height="180"></canvas>
        </div>

        {{-- Chart: Drowsy events --}}
        <div class="card p-4">
            <div class="section-label">
                <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Deteksi Kantuk & Alarm — {{ $filterLabel }}
            </div>
            <canvas id="chart-drowsy-daily" height="180"></canvas>
        </div>
    </div>

    {{-- ── Row 4: Tables (4 columns) ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-4">

        {{-- Top Drivers --}}
        <div class="card p-4">
            <div class="section-label">
                 Supir Paling Aktif — {{ $filterLabel }}
            </div>
            @if($topDrivers->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">Belum ada data.</p>
            @else
            <div>
                @foreach($topDrivers as $i => $td)
                <div class="row-item">
                    <div style="width:28px;height:28px;border-radius:50%;background:{{ $i === 0 ? '#fef3c7' : ($i === 1 ? '#f1f5f9' : '#f9fafb') }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:{{ $i === 0 ? '#d97706' : '#6b7280' }};flex-shrink:0;">
                        {{ $i + 1 }}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $td->driver->full_name ?? '—' }}</div>
                        <div style="font-size:10px;color:#9ca3af;">{{ $td->driver->driver_code ?? '' }}</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:13px;font-weight:700;color:#111827;">{{ $td->trip_count }} trip</div>
                        <div style="font-size:10px;color:#9ca3af;">{{ number_format($td->total_km ?? 0, 0) }} km</div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Top Vehicles --}}
        <div class="card p-4">
            <div class="section-label">
                 Kendaraan Terbanyak Jarak — {{ $filterLabel }}
            </div>
            @if($topVehicles->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">Belum ada data.</p>
            @else
            <div>
                @foreach($topVehicles as $i => $tv)
                <div class="row-item">
                    <div style="width:28px;height:28px;border-radius:50%;background:{{ $i === 0 ? '#fef3c7' : '#f9fafb' }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:{{ $i === 0 ? '#d97706' : '#6b7280' }};flex-shrink:0;">
                        {{ $i + 1 }}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $tv->vehicle->name ?? '—' }}</div>
                        <div style="font-size:10px;color:#9ca3af;">{{ $tv->vehicle->license_plate ?? '' }}</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:13px;font-weight:700;color:#111827;">{{ number_format($tv->total_km ?? 0, 0) }} km</div>
                        <div style="font-size:10px;color:#9ca3af;">{{ $tv->trip_count }} trip</div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Top Drowsy Drivers --}}
        <div class="card p-4">
            <div class="section-label">
                 Driver Sering Ngantuk — {{ $filterLabel }}
            </div>
            @if($topDrowsyDrivers->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">Belum ada data.</p>
            @else
            <div>
                @foreach($topDrowsyDrivers as $i => $dd)
                <div class="row-item">
                    <div style="width:28px;height:28px;border-radius:50%;background:{{ $i === 0 ? '#fee2e2' : ($i === 1 ? '#fff7ed' : '#f9fafb') }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:{{ $i === 0 ? '#dc2626' : ($i === 1 ? '#f97316' : '#6b7280') }};flex-shrink:0;">
                        {{ $i + 1 }}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $dd->driver->full_name ?? '—' }}</div>
                        <div style="font-size:10px;color:#9ca3af;">{{ $dd->driver->driver_code ?? '' }}</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:13px;font-weight:700;color:#f97316;">{{ $dd->drowsy_count }} <span style="font-size:10px;color:#9ca3af;font-weight:400;">event</span></div>
                        @if($dd->alarm_count > 0)
                        <div style="font-size:10px;font-weight:700;color:#dc2626;"> {{ $dd->alarm_count }} alarm</div>
                        @else
                        <div style="font-size:10px;color:#9ca3af;">0 alarm</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Right column: Alerts + SIM expiry --}}
        <div class="space-y-4">

            {{-- Recent unread alerts --}}
            <div class="card p-4">
                <div class="section-label">
                     Alert Terbaru
                    @if($recentAlerts->count() > 0)
                    <span style="margin-left:auto;padding:2px 8px;background:#fee2e2;color:#b91c1c;font-size:10px;font-weight:700;border-radius:99px;">{{ $recentAlerts->count() }} unread</span>
                    @endif
                </div>
                @if($recentAlerts->isEmpty())
                <p class="text-sm text-gray-400 text-center py-4">Tidak ada alert.</p>
                @else
                <div>
                    @foreach($recentAlerts->take(4) as $alert)
                    @php
                        $href = $alert->trip_id ? route('trips.show', $alert->trip_id)
                              : ($alert->vehicle_id ? "/live-map/{$alert->vehicle_id}" : '#');
                        $alertColor = $alert->severity === 'critical' ? '#dc2626' : '#f97316';
                        $alertBg    = $alert->severity === 'critical' ? '#fef2f2' : '#fff7ed';
                    @endphp
                    <a href="{{ $href }}" style="display:flex;gap:8px;padding:8px 0;border-bottom:1px solid #f9fafb;text-decoration:none;border-radius:6px;"
                       onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                        <div style="width:6px;height:6px;border-radius:50%;background:{{ $alertColor }};flex-shrink:0;margin-top:5px;"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:11px;font-weight:700;color:#111827;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $alert->title }}</div>
                            <div style="font-size:10px;color:#9ca3af;margin-top:1px;">{{ \Carbon\Carbon::parse($alert->triggered_at)->diffForHumans() }}</div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- SIM mau expired / sudah expired --}}
            @if($expiringDrivers->count() > 0)
            <div class="card p-4">
                <div class="section-label">
                     Status SIM Supir
                </div>
                @foreach($expiringDrivers as $d)
                @php
                    $expiry   = \Carbon\Carbon::parse($d->license_expiry);
                    $isExpired = $expiry->isPast();
                    $daysLeft  = (int) abs(now()->diffInDays($expiry));
                    if ($isExpired) {
                        $color     = '#dc2626';
                        $label     = "Sudah expired {$daysLeft} hari lalu";
                        $badgeBg   = '#fee2e2';
                        $badgeText = '#b91c1c';
                        $badgeLabel = 'EXPIRED';
                    } elseif ($daysLeft <= 14) {
                        $color     = '#dc2626';
                        $label     = "{$daysLeft} hari lagi";
                        $badgeBg   = '#fee2e2';
                        $badgeText = '#b91c1c';
                        $badgeLabel = 'KRITIS';
                    } else {
                        $color     = '#f97316';
                        $label     = "{$daysLeft} hari lagi";
                        $badgeBg   = '#fff7ed';
                        $badgeText = '#c2410c';
                        $badgeLabel = 'SEGERA';
                    }
                @endphp
                <div class="row-item">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:12px;font-weight:600;color:#111827;">{{ $d->full_name }}</div>
                        <div style="font-size:10px;color:#9ca3af;">{{ $d->driver_code }} · Exp: {{ $expiry->format('d/m/Y') }}</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="display:inline-block;padding:2px 7px;background:{{ $badgeBg }};color:{{ $badgeText }};border-radius:99px;font-size:9px;font-weight:700;margin-bottom:2px;">{{ $badgeLabel }}</div>
                        <div style="font-size:10px;font-weight:600;color:{{ $color }};">{{ $label }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// ── Data server → dashboard.js ───────────────────────────────────
window.__dashboard = {
    chartDays:   @json($chartDays),
    chartTrips:  @json($chartTrips),
    chartKm:     @json($chartKm),
    chartDrowsy: @json($chartDrowsy),
    chartAlarms: @json($chartAlarms),
};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/dashboard.js') }}"></script>
@endpush