<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class LiveMapController extends Controller
{
    public function index()
    {
        $vehicles = DB::table('v_vehicle_last_position')->get();

        return view('livemap.index', array_merge(
            compact('vehicles'),
            $this->mapConfig()
        ));
    }

    public function show(Vehicle $vehicle)
    {
        $trip = DB::table('v_active_trip_detail')
                  ->where('vehicle_id', $vehicle->id)
                  ->first();

        // Jika tidak ada trip aktif, cari trip terakhir yang selesai
        $lastTrip = null;
        if (!$trip) {
            $lastTrip = DB::table('trips')
                ->join('vehicles', 'trips.vehicle_id', '=', 'vehicles.id')
                ->leftJoin('drivers', 'trips.driver_id', '=', 'drivers.id')
                ->where('trips.vehicle_id', $vehicle->id)
                ->whereIn('trips.status', ['completed', 'cancelled'])
                ->orderByDesc('trips.arrived_at')
                ->select(
                    'trips.*',
                    'vehicles.name as vehicle_name',
                    'vehicles.license_plate',
                    'vehicles.vehicle_type',
                    'drivers.full_name as driver_name',
                    'drivers.phone as driver_phone'
                )
                ->first();
        }

        // Batasi track GPS ke jendela waktu trip yang sedang ditampilkan saja
        // (trip aktif, atau trip terakhir yang selesai). Tanpa batasan ini,
        // semua histori GPS kendaraan sepanjang masa akan tercampur jadi satu
        // jalur, termasuk milik trip-trip lain.
        $relevantTrip = $trip ?? $lastTrip;

        $gpsQuery = DB::table('gps_telemetry')->where('vehicle_id', $vehicle->id);

        if ($relevantTrip && $relevantTrip->departed_at) {
            $gpsQuery->where('gps_timestamp', '>=', $relevantTrip->departed_at);
            if ($relevantTrip->arrived_at) {
                $gpsQuery->where('gps_timestamp', '<=', $relevantTrip->arrived_at);
            }
        } else {
            // Belum ada trip yang berangkat — tampilkan saja posisi 24 jam terakhir
            $gpsQuery->where('gps_timestamp', '>=', now()->subHours(24));
        }

        $gpsPoints = $gpsQuery->orderBy('gps_timestamp')
                       ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);

        $latestDriverStatus = DB::table('driver_monitoring_events')
                                ->where('vehicle_id', $vehicle->id)
                                ->orderByDesc('recorded_at')
                                ->first();

        $vehicles = DB::table('v_vehicle_last_position')->get();

        return view('livemap.index', array_merge(
            compact('vehicle', 'trip', 'lastTrip', 'gpsPoints', 'latestDriverStatus', 'vehicles'),
            $this->mapConfig()
        ));
    }
}