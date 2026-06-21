<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\Request;
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

    public function tripDetail(\App\Models\Vehicle $vehicle)
    {
        // Ambil trip aktif atau completed terakhir
        $trip = DB::table('trips')
                ->where('vehicle_id', $vehicle->id)
                ->whereIn('status', ['in_progress', 'completed'])
                ->orderByDesc('updated_at')
                ->first();

        $fromTime = $trip && $trip->departed_at
            ? $trip->departed_at
            : now()->subHours(24)->toDateTimeString();

        $gpsTrack = DB::table('gps_telemetry')
                    ->where('vehicle_id', $vehicle->id)
                    ->where('gps_timestamp', '>=', $fromTime)
                    ->orderBy('gps_timestamp')
                    ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);

        $lastGps      = $gpsTrack->last();
        $currentSpeed = $lastGps ? (int) round($lastGps->speed_kmh) : 0;

        // ── ETA calculations ────────────────────────────────────────
        $etaData = $this->calcAllETA($trip, $lastGps, $currentSpeed);

        $driverStatus = DB::table('driver_monitoring_events')
                        ->where('vehicle_id', $vehicle->id)
                        ->orderByDesc('recorded_at')
                        ->value('driver_status');

        return response()->json([
            'trip' => $trip ? array_merge((array) $trip, [
                'current_lat'       => $lastGps?->latitude,
                'current_lng'       => $lastGps?->longitude,
                'current_speed_kmh' => $currentSpeed,
            ]) : null,
            'gps_track'     => $gpsTrack,
            'driver_status' => $driverStatus ?? 'normal',
            'current_speed' => $currentSpeed,
            'eta'           => $etaData,
        ]);
    }

    /**
     * Hitung semua varian ETA
     */
    private function calcAllETA($trip, $lastGps, float $currentSpeed): array
    {
        if (!$trip || $trip->status === 'completed') {
            return [
                'realtime_haversine' => null,
                'realtime_speed'     => $currentSpeed,
                'initial_haversine'  => null,
                'initial_minutes'    => null,
                'dest_lat'           => null,
                'dest_lng'           => null,
                'dist_to_dest'       => null,
            ];
        }

        $destLat = (float) $trip->dest_lat;
        $destLng = (float) $trip->dest_lng;

        // ── ETA real-time haversine ───────────────────────────────────
        $realtimeMin = null;
        if ($lastGps) {
            $curLat  = (float) $lastGps->latitude;
            $curLng  = (float) $lastGps->longitude;
            $distNow = $this->haversine($curLat, $curLng, $destLat, $destLng);

            $roadFactor  = $distNow < 3 ? 1.6 : ($distNow < 10 ? 1.4 : 1.25);
            $distRoad    = $distNow * $roadFactor;
            $speed       = $currentSpeed > 5
                ? $currentSpeed
                : ($distRoad < 5 ? 25 : ($distRoad < 15 ? 35 : 50));
            $delay       = $distRoad < 5 ? 5 : ($distRoad < 15 ? 4 : 3);
            $realtimeMin = (int) round(($distRoad / $speed) * 60 + $delay);

            // ── ETA real-time Google dengan traffic ───────────────────
            $realtimeGoogle = $this->fetchGoogleETAWithTraffic(
                $curLat, $curLng, $destLat, $destLng
            );

            $distM = round($distNow * 1000, 0);
        }

        // ── ETA initial haversine ────────────────────────────────────
        $initialMin = null;
        if ($trip->departed_at && $trip->estimated_arrival_at) {
            $diff       = now()->diffInMinutes(
                \Carbon\Carbon::parse($trip->estimated_arrival_at), false
            );
            $initialMin = $diff > 0 ? (int) $diff : 0;
        }

        return [
            'realtime_haversine' => $realtimeMin,
            'realtime_google'    => $realtimeGoogle ?? null, // ETA Google + traffic
            'realtime_speed'     => $currentSpeed,
            'initial_haversine'  => $initialMin,
            'initial_minutes'    => $initialMin,
            'dest_lat'           => $destLat,
            'dest_lng'           => $destLng,
            'dist_to_dest'       => $distM ?? null,
        ];
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

    private function fetchGoogleETAWithTraffic(
        float $oLat, float $oLng,
        float $dLat, float $dLng
    ): ?int {
        $key = config('services.google_maps.key');
        if (!$key) return null;

        try {
            $url = "https://maps.googleapis.com/maps/api/directions/json?" . http_build_query([
                'origin'         => "{$oLat},{$oLng}",
                'destination'    => "{$dLat},{$dLng}",
                'mode'           => 'driving',
                'departure_time' => 'now',          // ← aktifkan traffic real-time
                'traffic_model'  => 'best_guess',   // ← model traffic terbaik
                'key'            => $key,
            ]);

            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);

            if (!$response->ok()) return null;

            $data = $response->json();

            if ($data['status'] !== 'OK' || empty($data['routes'])) return null;

            $leg = $data['routes'][0]['legs'][0];

            // Pakai duration_in_traffic jika ada (traffic data tersedia)
            if (isset($leg['duration_in_traffic']['value'])) {
                return (int) round($leg['duration_in_traffic']['value'] / 60);
            }

            // Fallback ke duration biasa
            return (int) round($leg['duration']['value'] / 60);

        } catch (\Exception $e) {
            \Log::warning('Google ETA with traffic failed: ' . $e->getMessage());
            return null;
        }
    }

    public function alerts()
    {
        return response()->json(
            Alert::where('is_read', false)
                 ->with(['vehicle', 'driver'])
                 ->orderByDesc('triggered_at')
                 ->take(20)
                 ->get()
        );
    }

    public function search(Request $request)
    {
        $q    = $request->get('q', '');
        $type = $request->get('type', 'vehicle');

        if (strlen($q) < 2) return response()->json([]);

        if ($type === 'vehicle') {
            return response()->json(
                Vehicle::whereNull('deleted_at')
                    ->where(function($qb) use ($q) {
                        $qb->where('name', 'like', "%{$q}%")
                           ->orWhere('license_plate', 'like', "%{$q}%")
                           ->orWhere('vehicle_code', 'like', "%{$q}%");
                    })
                    ->select('id', 'name', 'license_plate', 'vehicle_code', 'status')
                    ->limit(6)
                    ->get()
            );
        }

        return response()->json(
            Driver::whereNull('deleted_at')
                ->where(function($qb) use ($q) {
                    $qb->where('full_name', 'like', "%{$q}%")
                       ->orWhere('driver_code', 'like', "%{$q}%")
                       ->orWhere('phone', 'like', "%{$q}%");
                })
                ->select('id', 'full_name', 'driver_code', 'status')
                ->limit(6)
                ->get()
        );
    }

    public function markAlertRead($id)
    {
        Alert::where('id', $id)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function markAlertsRead()
    {
        Alert::where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }
}