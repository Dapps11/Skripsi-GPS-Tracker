<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class LiveMapController extends Controller
{
    public function index()
    {
        $vehicles = DB::table('v_vehicle_last_position')->get();
        return view('livemap.index', compact('vehicles'));
    }

    public function show(Vehicle $vehicle)
    {
        $trip = DB::table('v_active_trip_detail')
                  ->where('vehicle_id', $vehicle->id)
                  ->first();

        $gpsPoints = DB::table('gps_telemetry')
                       ->where('vehicle_id', $vehicle->id)
                       ->orderBy('gps_timestamp')
                       ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);

        $latestDriverStatus = DB::table('driver_monitoring_events')
                                ->where('vehicle_id', $vehicle->id)
                                ->orderByDesc('recorded_at')
                                ->first();

        $vehicles = DB::table('v_vehicle_last_position')->get();

        return view('livemap.index', compact(
            'vehicle', 'trip', 'gpsPoints', 'latestDriverStatus', 'vehicles'
        ));
    }
}