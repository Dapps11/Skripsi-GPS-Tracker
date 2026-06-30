<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Trip;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\DriverMonitoringEvent;
use App\Models\GpsTelemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();

        // ── Filter periode ──────────────────────────────────────────
        $filter    = $request->input('filter', 'week'); // week, month, custom
        $startDate = null;
        $endDate   = null;

        switch ($filter) {
            case 'month':
                $startDate = $now->copy()->startOfMonth();
                $endDate   = $now->copy()->endOfDay();
                break;

            case 'custom':
                $startDate = $request->input('start_date')
                    ? Carbon::parse($request->input('start_date'))->startOfDay()
                    : $now->copy()->subDays(6)->startOfDay();
                $endDate = $request->input('end_date')
                    ? Carbon::parse($request->input('end_date'))->endOfDay()
                    : $now->copy()->endOfDay();
                break;

            case 'week':
            default:
                $filter    = 'week';
                $startDate = $now->copy()->subDays(6)->startOfDay();
                $endDate   = $now->copy()->endOfDay();
                break;
        }

        // Label periode untuk tampilan
        $filterLabel = match ($filter) {
            'month'  => 'Bulan Ini',
            'custom' => $startDate->format('d/m/Y') . ' — ' . $endDate->format('d/m/Y'),
            default  => 'Minggu Ini',
        };

        // Periode pembanding (untuk delta %)
        $rangeDays       = max(1, $startDate->diffInDays($endDate));
        $prevStart       = $startDate->copy()->subDays($rangeDays + 1)->startOfDay();
        $prevEnd         = $startDate->copy()->subDay()->endOfDay();

        // ── Fleet status saat ini (realtime, tidak dipengaruhi filter) ──
        $fleetSummary = DB::table('v_fleet_summary')->first();

        // ── Trip summary periode ─────────────────────────────────────
        $tripsInPeriod = Trip::whereBetween('created_at', [$startDate, $endDate]);
        $tripStats = [
            'total'       => (clone $tripsInPeriod)->count(),
            'completed'   => (clone $tripsInPeriod)->where('status', 'completed')->count(),
            'in_progress' => Trip::where('status', 'in_progress')->count(),
            'planned'     => Trip::where('status', 'planned')->count(),
        ];

        // Total jarak tempuh periode
        $totalDistanceKm = Trip::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('total_distance_km');

        // Total jarak periode sebelumnya (untuk perbandingan %)
        $prevDistanceKm = Trip::whereBetween('created_at', [$prevStart, $prevEnd])
            ->where('status', 'completed')
            ->sum('total_distance_km');

        // ── Grafik: trip selesai per hari dalam periode ───────────────
        $tripsByDay = Trip::where('status', 'completed')
            ->whereBetween('arrived_at', [$startDate, $endDate])
            ->selectRaw('DATE(arrived_at) as date, COUNT(*) as count, SUM(total_distance_km) as km')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartDays  = [];
        $chartTrips = [];
        $chartKm    = [];
        $totalDays  = (int) $startDate->diffInDays($endDate);
        for ($i = $totalDays; $i >= 0; $i--) {
            $d   = $endDate->copy()->subDays($i)->format('Y-m-d');
            $lbl = $endDate->copy()->subDays($i)->locale('id')->isoFormat('dd, D/M');
            $chartDays[]  = $lbl;
            $chartTrips[] = $tripsByDay->has($d) ? (int) $tripsByDay[$d]->count : 0;
            $chartKm[]    = $tripsByDay->has($d) ? round($tripsByDay[$d]->km ?? 0, 1) : 0;
        }

        // ── Grafik: drowsy events per hari dalam periode ─────────────
        $drowsyByDay = DriverMonitoringEvent::whereBetween('event_timestamp', [$startDate, $endDate])
            ->whereIn('event_type', ['drowsy', 'drowsy_warning', 'alarm'])
            ->selectRaw('DATE(event_timestamp) as date, COUNT(*) as count, SUM(is_alarm) as alarms')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartDrowsy = [];
        $chartAlarms = [];
        for ($i = $totalDays; $i >= 0; $i--) {
            $d = $endDate->copy()->subDays($i)->format('Y-m-d');
            $chartDrowsy[] = $drowsyByDay->has($d) ? (int) $drowsyByDay[$d]->count : 0;
            $chartAlarms[] = $drowsyByDay->has($d) ? (int) $drowsyByDay[$d]->alarms : 0;
        }

        // ── Top 5 supir aktif periode ─────────────────────────────────
        $topDrivers = Trip::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->whereNotNull('driver_id')
            ->selectRaw('driver_id, COUNT(*) as trip_count, SUM(total_distance_km) as total_km')
            ->groupBy('driver_id')
            ->orderByDesc('trip_count')
            ->orderByDesc('total_km')
            ->limit(5)
            ->with('driver:id,full_name,driver_code,status')
            ->get();

        // ── Top 5 kendaraan by jarak ──────────────────────────────────
        $topVehicles = Trip::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->selectRaw('vehicle_id, COUNT(*) as trip_count, SUM(total_distance_km) as total_km')
            ->groupBy('vehicle_id')
            ->orderByDesc('total_km')
            ->limit(5)
            ->with('vehicle:id,name,license_plate,status')
            ->get();

        // ── Top 5 driver paling sering ngantuk ────────────────────────
        $topDrowsyDrivers = DriverMonitoringEvent::whereBetween('event_timestamp', [$startDate, $endDate])
            ->whereIn('event_type', ['drowsy', 'drowsy_warning', 'alarm'])
            ->whereNotNull('driver_id')
            ->selectRaw('driver_id, COUNT(*) as drowsy_count, SUM(is_alarm) as alarm_count')
            ->groupBy('driver_id')
            ->orderByDesc('drowsy_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $item->driver = Driver::find($item->driver_id, ['id', 'full_name', 'driver_code', 'status']);
                return $item;
            });

        // ── Alert terbaru (unread — realtime, tidak dipengaruhi filter) ──
        $recentAlerts = Alert::where('is_read', false)
            ->with(['vehicle:id,name,license_plate', 'driver:id,full_name'])
            ->orderByDesc('triggered_at')
            ->limit(8)
            ->get();

        // ── Total alarm kantuk periode ────────────────────────────────
        $drowsyStatsMonth = [
            'total'  => DriverMonitoringEvent::whereBetween('event_timestamp', [$startDate, $endDate])
                            ->whereIn('event_type', ['drowsy', 'drowsy_warning', 'alarm'])
                            ->count(),
            'alarms' => DriverMonitoringEvent::whereBetween('event_timestamp', [$startDate, $endDate])
                            ->where('is_alarm', 1)
                            ->count(),
        ];

        // ── SIM supir mau expired (≤ 60 hari) atau sudah expired ────
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
            'prevDistanceKm',
            'chartDays',
            'chartTrips',
            'chartKm',
            'chartDrowsy',
            'chartAlarms',
            'topDrivers',
            'topVehicles',
            'topDrowsyDrivers',
            'recentAlerts',
            'drowsyStatsMonth',
            'expiringDrivers',
            'filter',
            'filterLabel',
            'startDate',
            'endDate'
        ));
    }
}