<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GpsTelemetry;
use App\Models\DriverMonitoringEvent;
use App\Models\IotDevice;
use App\Models\Vehicle;
use App\Models\Alert;
use Carbon\Carbon;

class IoTApiController extends Controller
{
    /**
     * SIM7600 kirim data GPS
     * POST /api/telemetry
     */
    public function receiveTelemetry(Request $request)
    {
        $data = $request->validate([
            'device_id'     => 'required|string',
            'latitude'      => 'required|numeric',
            'longitude'     => 'required|numeric',
            'speed_kmh'     => 'required|numeric',
            'heading'       => 'nullable|numeric',
            'gsm_signal'    => 'nullable|integer',
            'network_type'  => 'nullable|in:2G,3G,4G,5G',
            'gps_timestamp' => 'nullable|string',
        ]);

        $device = IotDevice::where('device_id', $data['device_id'])->first();
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        GpsTelemetry::create([
            'device_id'     => $device->id,
            'vehicle_id'    => $device->vehicle_id,
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'speed_kmh'     => $data['speed_kmh'],
            'heading'       => $data['heading'] ?? null,
            'gsm_signal'    => $data['gsm_signal'] ?? null,
            'network_type'  => $data['network_type'] ?? null,
            'gps_timestamp' => isset($data['gps_timestamp']) ? Carbon::parse($data['gps_timestamp']) : now(),
            'recorded_at'   => now(),
        ]);

        $device->update([
            'last_latitude'  => $data['latitude'],
            'last_longitude' => $data['longitude'],
            'last_speed_kmh' => $data['speed_kmh'],
            'last_heartbeat' => now(),
            'status'         => $data['speed_kmh'] > 2 ? 'online' : 'idle',
        ]);

        if ($device->vehicle_id) {
            Vehicle::where('id', $device->vehicle_id)->update([
                'status' => $data['speed_kmh'] > 2 ? 'moving' : 'idle',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * OpenMV kirim event kantuk
     * POST /api/drowsy
     */
    public function receiveDrowsy(Request $request)
    {
        $data = $request->validate([
            'device_id'     => 'required|string',
            'event_type'    => 'required|in:normal,drowsy_warning,drowsy_alert,eyes_closed,yawning,distracted,no_face_detected',
            'confidence'    => 'nullable|numeric|min:0|max:1',
            'driver_status' => 'required|in:normal,warning,danger',
        ]);

        $device = IotDevice::where('device_id', $data['device_id'])->first();
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        DriverMonitoringEvent::create([
            'device_id'       => $device->id,
            'vehicle_id'      => $device->vehicle_id,
            'driver_id'       => $device->driver_id,
            'event_type'      => $data['event_type'],
            'confidence'      => $data['confidence'] ?? null,
            'driver_status'   => $data['driver_status'],
            'event_timestamp' => now(),
            'recorded_at'     => now(),
        ]);

        if (in_array($data['driver_status'], ['warning', 'danger'])) {
            Alert::create([
                'alert_type'   => 'drowsy_driver',
                'severity'     => $data['driver_status'] === 'danger' ? 'critical' : 'warning',
                'vehicle_id'   => $device->vehicle_id,
                'driver_id'    => $device->driver_id,
                'device_id'    => $device->id,
                'title'        => 'Peringatan Kantuk - ' . optional($device->vehicle)->name,
                'message'      => 'Terdeteksi ' . $data['event_type'] . ' (confidence: ' . round(($data['confidence'] ?? 0) * 100) . '%)',
                'triggered_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}