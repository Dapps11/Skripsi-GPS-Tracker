<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\IotDevice;
use App\Models\GpsTelemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TripController extends Controller
{
    public function index()
    {
        $trips = Trip::with(['vehicle', 'driver'])
                     ->orderByDesc('created_at')
                     ->paginate(15);

        $summary = [
            'planned'     => Trip::where('status', 'planned')->count(),
            'in_progress' => Trip::where('status', 'in_progress')->count(),
            'completed'   => Trip::where('status', 'completed')->count(),
        ];

        return view('trips.index', compact('trips', 'summary'));
    }

    public function create()
    {
        $vehicles = Vehicle::whereNull('deleted_at')->orderBy('name')->get();
        $drivers  = Driver::whereNull('deleted_at')
                        ->whereIn('status', ['available', 'on_duty'])
                        ->orderBy('full_name')->get();

        return view('trips.create', array_merge(
            compact('vehicles', 'drivers'),
            $this->mapConfig()
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id'           => 'required|exists:vehicles,id',
            'driver_id'            => 'nullable|exists:drivers,id',
            'origin_name'          => 'required|string|max:150',
            'origin_address'       => 'nullable|string',
            'origin_lat'           => 'required|numeric|between:-90,90',
            'origin_lng'           => 'required|numeric|between:-180,180',
            'dest_name'            => 'required|string|max:150',
            'dest_address'         => 'nullable|string',
            'dest_lat'             => 'required|numeric|between:-90,90',
            'dest_lng'             => 'required|numeric|between:-180,180',
            'estimated_arrival_at' => 'nullable|string',
            'notes'                => 'nullable|string',
        ]);

        // Pastikan string kosong dikonversi ke null
        $validated['driver_id']            = $validated['driver_id'] ?: null;
        $validated['notes']                = $validated['notes'] ?: null;
        $validated['origin_address']       = $validated['origin_address'] ?: null;
        $validated['dest_address']         = $validated['dest_address'] ?: null;
        $validated['estimated_arrival_at'] = $validated['estimated_arrival_at'] ?: null;

        // Generate trip code
        $validated['trip_code']   = 'TRIP-' . strtoupper(substr(uniqid(), -6));
        $validated['status']      = 'planned';
        $validated['departed_at'] = null;

        // Cari device terpasang di kendaraan
        $device = \App\Models\IotDevice::where('vehicle_id', $validated['vehicle_id'])
                                    ->whereNull('deleted_at')
                                    ->first();

        if ($device) {
            $validated['device_id'] = $device->id;
            if (empty($validated['driver_id']) && $device->driver_id) {
                $validated['driver_id'] = $device->driver_id;
            }
        }

        $trip = Trip::create($validated);

        return redirect()->route('trips.index')
                        ->with('success', "Trip {$trip->trip_code} berhasil dibuat. Waktu berangkat otomatis tercatat saat kendaraan mulai bergerak.");
    }

    public function show(Trip $trip)
    {
        $trip->load(['vehicle', 'driver', 'device']);

        $gpsPoints = GpsTelemetry::where('trip_id', $trip->id)
                                ->orderBy('gps_timestamp')
                                ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);

        if ($gpsPoints->isEmpty() && $trip->departed_at) {
            $query = GpsTelemetry::where('vehicle_id', $trip->vehicle_id)
                                ->where('gps_timestamp', '>=', $trip->departed_at);
            if ($trip->arrived_at) {
                $query->where('gps_timestamp', '<=',
                    \Carbon\Carbon::parse($trip->arrived_at)->addMinutes(5)
                );
            }
            $gpsPoints = $query->orderBy('gps_timestamp')
                            ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);
        }

        if (!$trip->total_distance_km && $gpsPoints->count() >= 2) {
            $totalKm = 0.0;
            for ($i = 1; $i < $gpsPoints->count(); $i++) {
                $totalKm += $this->haversine(
                    $gpsPoints[$i-1]->latitude, $gpsPoints[$i-1]->longitude,
                    $gpsPoints[$i]->latitude,   $gpsPoints[$i]->longitude
                );
            }
            $totalKm = round($totalKm, 2);
            $trip->update(['total_distance_km' => $totalKm]);
            $trip->total_distance_km = $totalKm;
        }

        // Hitung ETA awal dengan haversine
        $etaHaversine = null;
        if ($trip->origin_lat && $trip->dest_lat) {
            $dist       = $this->haversine(
                $trip->origin_lat, $trip->origin_lng,
                $trip->dest_lat,   $trip->dest_lng
            );
            $rf         = $dist < 3 ? 1.6 : ($dist < 10 ? 1.4 : 1.25);
            $distRoad   = $dist * $rf;
            $speed      = $distRoad < 5 ? 25 : ($distRoad < 15 ? 35 : 50);
            $delay      = $distRoad < 5 ? 5  : ($distRoad < 15 ? 4  : 3);
            $etaHaversine = (int) round(($distRoad / $speed) * 60 + $delay);
        }

        $mapType       = session('map_type', 'osm');
        $googleMapsKey = config('services.google_maps.key', '');

        return view('trips.show', compact(
            'trip', 'gpsPoints', 'mapType', 'googleMapsKey', 'etaHaversine'
        ));
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat/2)**2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }

    public function edit(Trip $trip)
    {
        $vehicles = Vehicle::whereNull('deleted_at')->orderBy('name')->get();
        $drivers  = Driver::whereNull('deleted_at')->orderBy('full_name')->get();
        return view('trips.edit', compact('trip', 'vehicles', 'drivers'));
    }

    public function update(Request $request, Trip $trip)
    {
        $validated = $request->validate([
            'vehicle_id'           => 'required|exists:vehicles,id',
            'driver_id'            => 'nullable|exists:drivers,id',
            'origin_name'          => 'required|string|max:150',
            'origin_address'       => 'nullable|string',
            'origin_lat'           => 'required|numeric|between:-90,90',
            'origin_lng'           => 'required|numeric|between:-180,180',
            'dest_name'            => 'required|string|max:150',
            'dest_address'         => 'nullable|string',
            'dest_lat'             => 'required|numeric|between:-90,90',
            'dest_lng'             => 'required|numeric|between:-180,180',
            'departed_at'          => 'required|date',
            'estimated_arrival_at' => 'nullable|date|after:departed_at',
            'status'               => 'required|in:planned,in_progress,completed,cancelled',
            'notes'                => 'nullable|string',
        ]);

        // Jika status completed → set arrived_at
        if ($validated['status'] === 'completed' && !$trip->arrived_at) {
            $validated['arrived_at'] = now();
        }

        $trip->update($validated);

        return redirect()->route('trips.index')
                         ->with('success', "Trip {$trip->trip_code} diperbarui.");
    }

    public function destroy(Trip $trip)
    {
        // Hanya boleh hapus trip yang belum in_progress
        if ($trip->status === 'in_progress') {
            return back()->withErrors(['error' => 'Trip yang sedang berjalan tidak bisa dihapus.']);
        }

        $trip->delete();
        return redirect()->route('trips.index')
                         ->with('success', 'Trip berhasil dihapus.');
    }

    /**
     * Tandai trip sebagai selesai
     */
    public function complete(Trip $trip)
    {
        $trip->update([
            'status'     => 'completed',
            'arrived_at' => now(),
        ]);

        return back()->with('success', "Trip {$trip->trip_code} ditandai selesai.");
    }
    

    /**
     * Mulai trip (ubah planned → in_progress)
     */
    public function start(Trip $trip)
    {
        if ($trip->status !== 'planned') {
            return back()->withErrors(['error' => 'Trip ini tidak bisa dimulai.']);
        }

        $trip->update([
            'status'      => 'in_progress',
            'departed_at' => now(),
        ]);

        return back()->with('success', "Trip {$trip->trip_code} dimulai.");
    }
    
}