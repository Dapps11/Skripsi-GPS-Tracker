<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function fleetSummary()
    {
        return response()->json(DB::table('v_fleet_summary')->first());
    }

    public function vehiclesPosition()
    {
        return response()->json(
            DB::table('v_vehicle_last_position')
              ->whereNotNull('latitude')
              ->get()
        );
    }

    public function tripDetail(Vehicle $vehicle)
    {
        $trip = DB::table('v_active_trip_detail')
                  ->where('vehicle_id', $vehicle->id)
                  ->first();

        $gpsTrack = DB::table('gps_telemetry')
                      ->where('vehicle_id', $vehicle->id)
                      ->orderBy('gps_timestamp')
                      ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);

        $driverStatus = DB::table('driver_monitoring_events')
                          ->where('vehicle_id', $vehicle->id)
                          ->orderByDesc('recorded_at')
                          ->value('driver_status');

        return response()->json([
            'trip'          => $trip,
            'gps_track'     => $gpsTrack,
            'driver_status' => $driverStatus ?? 'normal',
        ]);
    }

    public function alerts()
    {
        return response()->json(
            Alert::where('is_read', false)
                 ->with(['vehicle', 'driver'])
                 ->orderByDesc('triggered_at')
                 ->take(10)
                 ->get()
        );
    }
}