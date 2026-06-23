<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Trip;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\DriverMonitoringEvent;
use App\Models\GpsTelemetry;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now       = Carbon::now();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
        $today     = $now->copy()->startOfDay();
        $last7days = $now->copy()->subDays(6)->startOfDay();

        // ── Fleet status saat ini ─────────────────────────────────────────
        $fleetSummary = DB::table('v_fleet_summary')->first();

        // ── Trip summary bulan ini ────────────────────────────────────────
        $tripsThisMonth = Trip::where('created_at', '>=', $thisMonth);
        $tripStats = [
            'total'       => (clone $tripsThisMonth)->count(),
            'completed'   => (clone $tripsThisMonth)->where('status', 'completed')->count(),
            'in_progress' => Trip::where('status', 'in_progress')->count(),
            'planned'     => Trip::where('status', 'planned')->count(),
        ];

        // Total jarak tempuh bulan ini (km)
        $totalDistanceKm = Trip::where('created_at', '>=', $thisMonth)
            ->where('status', 'completed')
            ->sum('total_distance_km');

        // Total jarak bulan lalu (untuk perbandingan %)
        $lastMonthDistanceKm = Trip::whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->where('status', 'completed')
            ->sum('total_distance_km');

        // ── Grafik: trip selesai per hari 7 hari terakhir ────────────────
        $tripsByDay = Trip::where('status', 'completed')
            ->where('arrived_at', '>=', $last7days)
            ->selectRaw('DATE(arrived_at) as date, COUNT(*) as count, SUM(total_distance_km) as km')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartDays   = [];
        $chartTrips  = [];
        $chartKm     = [];
        for ($i = 6; $i >= 0; $i--) {
            $d   = $now->copy()->subDays($i)->format('Y-m-d');
            $lbl = $now->copy()->subDays($i)->locale('id')->isoFormat('dddd, D/M');
            $chartDays[]  = $lbl;
            $chartTrips[] = $tripsByDay->has($d) ? (int) $tripsByDay[$d]->count : 0;
            $chartKm[]    = $tripsByDay->has($d) ? round($tripsByDay[$d]->km ?? 0, 1) : 0;
        }

        // ── Grafik: drowsy events per hari 7 hari terakhir ───────────────
        $drowsyByDay = DriverMonitoringEvent::where('event_timestamp', '>=', $last7days)
            ->whereIn('event_type', ['drowsy', 'drowsy_warning', 'alarm'])
            ->selectRaw('DATE(event_timestamp) as date, COUNT(*) as count, SUM(is_alarm) as alarms')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartDrowsy = [];
        $chartAlarms = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->copy()->subDays($i)->format('Y-m-d');
            $chartDrowsy[] = $drowsyByDay->has($d) ? (int) $drowsyByDay[$d]->count : 0;
            $chartAlarms[] = $drowsyByDay->has($d) ? (int) $drowsyByDay[$d]->alarms : 0;
        }

        // ── Top 5 supir aktif bulan ini ───────────────────────────────────
        $topDrivers = Trip::where('created_at', '>=', $thisMonth)
            ->where('status', 'completed')
            ->whereNotNull('driver_id')
            ->selectRaw('driver_id, COUNT(*) as trip_count, SUM(total_distance_km) as total_km')
            ->groupBy('driver_id')
            ->orderByDesc('trip_count')
            ->limit(5)
            ->with('driver:id,full_name,driver_code,status')
            ->get();

        // ── Top 5 kendaraan by jarak ──────────────────────────────────────
        $topVehicles = Trip::where('created_at', '>=', $thisMonth)
            ->where('status', 'completed')
            ->selectRaw('vehicle_id, COUNT(*) as trip_count, SUM(total_distance_km) as total_km')
            ->groupBy('vehicle_id')
            ->orderByDesc('total_km')
            ->limit(5)
            ->with('vehicle:id,name,license_plate,status')
            ->get();

        // ── Alert terbaru (unread) ────────────────────────────────────────
        $recentAlerts = Alert::where('is_read', false)
            ->with(['vehicle:id,name,license_plate', 'driver:id,full_name'])
            ->orderByDesc('triggered_at')
            ->limit(8)
            ->get();

        // ── Total alarm kantuk bulan ini ──────────────────────────────────
        $drowsyStatsMonth = [
            'total'  => DriverMonitoringEvent::where('event_timestamp', '>=', $thisMonth)
                            ->whereIn('event_type', ['drowsy', 'drowsy_warning', 'alarm'])
                            ->count(),
            'alarms' => DriverMonitoringEvent::where('event_timestamp', '>=', $thisMonth)
                            ->where('is_alarm', 1)
                            ->count(),
        ];

        // ── SIM supir mau expired (≤ 60 hari) atau sudah expired ────────
        $expiringDrivers = Driver::whereNull('deleted_at')
            ->whereNotNull('license_expiry')
            ->whereDate('license_expiry', '<=', $now->copy()->addDays(60)->toDateString())
            ->orderBy('license_expiry')
            ->limit(8)
            ->get(['id', 'full_name', 'driver_code', 'license_expiry']);

        return view('dashboard.index', compact(
            'fleetSummary',
            'tripStats',
            'totalDistanceKm',
            'lastMonthDistanceKm',
            'chartDays',
            'chartTrips',
            'chartKm',
            'chartDrowsy',
            'chartAlarms',
            'topDrivers',
            'topVehicles',
            'recentAlerts',
            'drowsyStatsMonth',
            'expiringDrivers'
        ));
    }
}