<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GpsTelemetry;
use App\Models\DriverMonitoringEvent;
use App\Models\IotDevice;
use App\Models\Vehicle;
use App\Models\Alert;
use App\Models\Trip;
use App\Events\VehiclePositionUpdated;
use App\Events\FleetStatusUpdated;
use App\Events\TripStatusUpdated;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IoTApiController extends Controller
{
    const MOVING_SPEED  = 2;
    const ARRIVAL_DIST  = 0.03; // 30 meter

    public function receiveTelemetry(Request $request)
    {
        $data = $request->validate([
            'device_id'     => 'required|string',
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'speed_kmh'     => 'required|numeric|min:0',
            'heading'       => 'nullable|numeric',
            'accuracy_m'    => 'nullable|numeric',
            'satellites'    => 'nullable|integer',
            'hdop'          => 'nullable|numeric',
            'pdop'          => 'nullable|numeric',
            'vdop'          => 'nullable|numeric',
            'fix_mode'      => 'nullable|integer',
            'gsm_signal'    => 'nullable|integer',
            'network_type'  => 'nullable|string',
            'gps_timestamp' => 'nullable|string',
        ]);

        $device = IotDevice::where('device_id', $data['device_id'])
                           ->with(['vehicle', 'driver'])
                           ->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $isMoving      = $data['speed_kmh'] > self::MOVING_SPEED;
        $deviceStatus  = $isMoving ? 'online' : 'idle';
        $vehicleStatus = $isMoving ? 'moving' : 'idle';

        // Simpan GPS
        $telemetry = GpsTelemetry::create([
            'device_id'     => $device->id,
            'vehicle_id'    => $device->vehicle_id,
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'speed_kmh'     => $data['speed_kmh'],
            'heading'       => $data['heading']    ?? null,
            'accuracy_m'    => $data['accuracy_m'] ?? null,
            'satellites'    => $data['satellites'] ?? null,
            'hdop'          => $data['hdop']       ?? null,
            'pdop'          => $data['pdop']       ?? null,
            'vdop'          => $data['vdop']       ?? null,
            'fix_mode'      => $data['fix_mode']   ?? null,
            'gsm_signal'    => $data['gsm_signal'] ?? null,
            'network_type'  => $data['network_type'] ?? null,
            'gps_timestamp' => isset($data['gps_timestamp'])
                ? Carbon::parse($data['gps_timestamp'])
                : now(),
            'recorded_at'   => now(),
        ]);

        // Update device
        $device->update([
            'last_latitude'  => $data['latitude'],
            'last_longitude' => $data['longitude'],
            'last_speed_kmh' => $data['speed_kmh'],
            'last_heartbeat' => now(),
            'status'         => $deviceStatus,
        ]);

        // Update vehicle
        if ($device->vehicle_id) {
            Vehicle::where('id', $device->vehicle_id)
                   ->update(['status' => $vehicleStatus]);
        }

        // Handle trip logic
        $activeTrip = null;
        if ($device->vehicle_id) {
            $activeTrip = $this->handleTripLogic(
                $device,
                $data['latitude'],
                $data['longitude'],
                $data['speed_kmh'],
                $isMoving,
                $telemetry
            );
        }

        // ── BROADCAST via WebSocket ───────────────────────────────
        $this->broadcastUpdates($device, $data, $vehicleStatus, $activeTrip);

        return response()->json([
            'ok'     => true,
            'status' => $deviceStatus,
        ]);
    }

    private function broadcastUpdates(
        IotDevice $device,
        array $data,
        string $vehicleStatus,
        ?Trip $activeTrip
    ): void {
        // 1. Broadcast posisi kendaraan
        if ($device->vehicle_id) {
            broadcast(new VehiclePositionUpdated([
                'vehicle_id'     => $device->vehicle_id,
                'vehicle_name'   => optional($device->vehicle)->name,
                'license_plate'  => optional($device->vehicle)->license_plate,
                'vehicle_status' => $vehicleStatus,
                'driver_name'    => optional($device->driver)->full_name,
                'latitude'       => $data['latitude'],
                'longitude'      => $data['longitude'],
                'speed_kmh'      => round($data['speed_kmh']),
                'heading'        => $data['heading'] ?? null,
                'updated_at'     => now()->toISOString(),
            ]))->toOthers();
        }

        // 2. Broadcast fleet summary
        $summary = DB::table('v_fleet_summary')->first();
        if ($summary) {
            broadcast(new FleetStatusUpdated([
                'moving'         => $summary->moving  ?? 0,
                'idle'           => $summary->idle    ?? 0,
                'offline'        => $summary->offline ?? 0,
                'total_vehicles' => $summary->total_vehicles ?? 0,
                'online'         => $summary->online ?? 0,
            ]))->toOthers();
        }

        // 3. Broadcast trip status jika ada
        if ($activeTrip && $device->vehicle_id) {
            broadcast(new TripStatusUpdated($device->vehicle_id, [
                'trip_id'    => $activeTrip->id,
                'status'     => $activeTrip->status,
                'trip_code'  => $activeTrip->trip_code,
                'current_lat'  => $data['latitude'],
                'current_lng'  => $data['longitude'],
                'current_speed'=> round($data['speed_kmh']),
            ]))->toOthers();
        }
    }

    private function handleTripLogic(
        IotDevice $device,
        float $lat,
        float $lng,
        float $speed,
        bool $isMoving,
        ?GpsTelemetry $telemetry = null
    ): ?Trip {
        // Urutkan berdasarkan departed_at (bukan created_at) supaya kalau
        // ada lebih dari satu trip 'in_progress' untuk kendaraan yang sama
        // (misal trip lama tidak ditutup dengan benar), yang dianggap aktif
        // adalah trip yang paling baru BERANGKAT.
        $activeTrip = Trip::where('vehicle_id', $device->vehicle_id)
                          ->where('status', 'in_progress')
                          ->latest('departed_at')->first();

        if (!$activeTrip && $isMoving) {
            $plannedTrip = Trip::where('vehicle_id', $device->vehicle_id)
                               ->where('status', 'planned')
                               ->whereNull('departed_at')
                               ->latest()->first();

            if ($plannedTrip) {
                $dist      = $this->haversine(
                    $plannedTrip->origin_lat, $plannedTrip->origin_lng,
                    $plannedTrip->dest_lat,   $plannedTrip->dest_lng
                );
                $distRoad  = $dist * 1.3;
                $durationM = (int) round(($distRoad / 40) * 60);

                $plannedTrip->update([
                    'status'               => 'in_progress',
                    'departed_at'          => now(),
                    'estimated_arrival_at' => now()->addMinutes($durationM),
                    'device_id'            => $device->id,
                ]);
                $activeTrip = $plannedTrip;
            }
        }

        if (!$activeTrip) return null;

        // Tag trip_id langsung ke record GPS yang baru dibuat di request ini
        // (by ID), bukan mencari "record terbaru yang trip_id-nya NULL" secara
        // global. Cara lama itu rawan salah tag: kalau ada backlog record lama
        // yang trip_id-nya masih NULL (misal dari saat kendaraan idle/tidak
        // ada trip aktif), atau dua request datang nyaris bersamaan, record
        // yang ditag bisa jadi bukan yang seharusnya.
        if ($telemetry && !$telemetry->trip_id) {
            $telemetry->update(['trip_id' => $activeTrip->id]);
        }

        // Cek arrival
        $distToDest = $this->haversine($lat, $lng, $activeTrip->dest_lat, $activeTrip->dest_lng);

        if ($distToDest <= self::ARRIVAL_DIST) {
            $totalDist = $this->calcTotalDistance($activeTrip->id);
            $activeTrip->update([
                'status'            => 'completed',
                'arrived_at'        => now(),
                'total_distance_km' => $totalDist,
            ]);
            Vehicle::where('id', $device->vehicle_id)->update(['status' => 'idle']);
            $device->update(['status' => 'idle']);

            Alert::create([
                'alert_type'   => 'trip_completed',
                'severity'     => 'info',
                'vehicle_id'   => $device->vehicle_id,
                'driver_id'    => $device->driver_id,
                'device_id'    => $device->id,
                'title'        => 'Trip Selesai — ' . optional($device->vehicle)->name,
                'message'      => "Kendaraan tiba di {$activeTrip->dest_name}.",
                'triggered_at' => now(),
            ]);
        }

        return $activeTrip;
    }

    private function calcTotalDistance(int $tripId): float
    {
        $points = GpsTelemetry::where('trip_id', $tripId)
                               ->orderBy('gps_timestamp')
                               ->get(['latitude', 'longitude']);
        $total = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $total += $this->haversine(
                $points[$i-1]->latitude, $points[$i-1]->longitude,
                $points[$i]->latitude,   $points[$i]->longitude
            );
        }
        return round($total, 2);
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

    public function receiveDrowsy(Request $request)
    {
        // 1. Validasi format asli dari Python
        $data = $request->validate([
            'device_id'  => 'required|string',
            'event_type' => 'required|string', // 'drowsy' atau 'alarm'
            'status'     => 'required|string', // 'AWAKE' atau 'DROWSY'
            'reasons'    => 'nullable|array',
            'perclos'    => 'nullable|numeric',
            'ear'        => 'nullable|numeric',
            'mar'        => 'nullable|numeric',
            'alarm'      => 'required|boolean',
        ]);

        // 2. Cari Device beserta Relasinya
        $device = IotDevice::where('device_id', $data['device_id'])->first();
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        // 3. Cari Trip (Perjalanan) yang sedang aktif untuk kendaraan ini
        $activeTrip = null;
        if ($device->vehicle_id) {
            $activeTrip = Trip::where('vehicle_id', $device->vehicle_id)
                              ->where('status', 'in_progress')
                              ->latest()
                              ->first();
        }

        $reasonString = !empty($data['reasons']) ? implode(', ', $data['reasons']) : 'Terdeteksi mengantuk';

        // 4. Simpan ke tabel driver_monitoring_events dengan relasi lengkap!
        DriverMonitoringEvent::create([
            'device_id'       => $device->id,
            'vehicle_id'      => $device->vehicle_id,
            'driver_id'       => $device->driver_id,
            'trip_id'         => $activeTrip ? $activeTrip->id : null, // <-- Otomatis terisi!
            'event_type'      => $data['event_type'], 
            'reasons'         => $reasonString,
            'perclos_value'   => $data['perclos'] ?? null,
            'ear_value'       => $data['ear'] ?? null,
            'mar_value'       => $data['mar'] ?? null,
            'is_alarm'        => $data['alarm'] ?? false,
            'event_timestamp' => now(),
            'recorded_at'     => now(),
        ]);

        // 5. Trigger Alert ke Dashboard (Truk Logistik)
        $severityLevel = $data['event_type'] === 'alarm' ? 'critical' : 'warning';
        $alertTitle    = $data['event_type'] === 'alarm' ? 'BAHAYA: Sopir Tertidur!' : 'Peringatan: Sopir Mulai Kelelahan';

        Alert::create([
            'alert_type'   => 'drowsy_driver',
            'severity'     => $severityLevel,
            'vehicle_id'   => $device->vehicle_id,
            'driver_id'    => $device->driver_id,
            'device_id'    => $device->id,
            'title'        => $alertTitle . ' — ' . optional($device->vehicle)->name,
            'message'      => 'Sistem mendeteksi: ' . $reasonString,
            'triggered_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}