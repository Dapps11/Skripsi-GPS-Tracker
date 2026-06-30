<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class LiveMapController extends Controller
{
    public function index()
    {
        $vehicles = DB::table('vehicles as v')
            ->leftJoin('iot_devices as i', 'v.id', '=', 'i.vehicle_id')
            ->leftJoin('drivers as d', 'i.driver_id', '=', 'd.id')
            ->whereNull('v.deleted_at')
            ->select(
                'v.id as vehicle_id', 'v.name as vehicle_name', 'v.license_plate', 'v.status as vehicle_status', 'v.vehicle_code',
                'i.last_latitude as latitude', 'i.last_longitude as longitude',
                'i.last_speed_kmh as speed_kmh', 'i.last_heartbeat as updated_at',
                'd.full_name as driver_name'
            )->get();

        return view('livemap.index', array_merge(
            compact('vehicles'),
            $this->mapConfig()
        ));
    }

    public function show(Vehicle $vehicle)
    {
        $trip = DB::table('trips as t')
            ->join('vehicles as v', 't.vehicle_id', '=', 'v.id')
            ->leftJoin('drivers as d', 't.driver_id', '=', 'd.id')
            ->where('t.status', 'in_progress')
            ->where('t.vehicle_id', $vehicle->id)
            ->select(
                't.*', 'v.name as vehicle_name', 'v.license_plate', 'v.vehicle_type',
                'd.full_name as driver_name', 'd.phone as driver_phone'
            )->first();

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
                    'vehicles.id as vehicle_id',
                    'vehicles.name as vehicle_name',
                    'vehicles.license_plate',
                    'vehicles.status as vehicle_status',
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

        $vehicles = DB::table('vehicles as v')
            ->leftJoin('iot_devices as i', 'v.id', '=', 'i.vehicle_id')
            ->leftJoin('drivers as d', 'i.driver_id', '=', 'd.id')
            ->whereNull('v.deleted_at')
            ->select(
                'v.id as vehicle_id', 'v.name as vehicle_name', 'v.license_plate', 'v.status as vehicle_status', 'v.vehicle_code',
                'i.last_latitude as latitude', 'i.last_longitude as longitude',
                'i.last_speed_kmh as speed_kmh', 'i.last_heartbeat as updated_at',
                'd.full_name as driver_name'
            )->get();

        return view('livemap.index', array_merge(
            compact('vehicle', 'trip', 'lastTrip', 'gpsPoints', 'latestDriverStatus', 'vehicles'),
            $this->mapConfig()
        ));
    }
}