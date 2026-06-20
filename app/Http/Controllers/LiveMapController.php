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

        $gpsPoints = DB::table('gps_telemetry')
                       ->where('vehicle_id', $vehicle->id)
                       ->orderBy('gps_timestamp')
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