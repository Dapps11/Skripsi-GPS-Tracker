<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IotDevice;
use App\Models\Vehicle;
use App\Models\GpsTelemetry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Broadcast;

class MarkOfflineDevices extends Command
{
    protected $signature   = 'devices:mark-offline';
    protected $description = 'Mark devices offline jika tidak ada heartbeat > 10 menit, idle jika speed=0 > 15 menit';

    public function handle(): void
    {
        $now            = Carbon::now();
        $offlineThresh  = $now->copy()->subMinutes(10);  // heartbeat > 10 menit → offline
        $idleThresh     = $now->copy()->subMinutes(15);  // speed=0 > 15 menit → idle

        // ── 1. Online/Idle → Offline (tidak ada heartbeat > 10 menit) ────────
        $offlineDevices = IotDevice::whereIn('status', ['online', 'idle'])
            ->where(function ($q) use ($offlineThresh) {
                $q->where('last_heartbeat', '<', $offlineThresh)
                  ->orWhereNull('last_heartbeat');
            })->get();

        foreach ($offlineDevices as $device) {
            $device->update(['status' => 'offline']);

            if ($device->vehicle_id) {
                Vehicle::where('id', $device->vehicle_id)
                       ->update(['status' => 'offline']);
            }

            $this->line("  [offline] Device {$device->device_id} — no heartbeat since " .
                        ($device->last_heartbeat ?? 'never'));
        }

        if ($offlineDevices->count() > 0) {
            $this->info("Marked {$offlineDevices->count()} device(s) as offline.");
        }

        // ── 2. Moving → Idle (speed = 0 selama > 15 menit) ──────────────────
        // Cek device yang masih 'online' (moving) tapi telemetri terakhirnya speed=0 > 15 menit
        $movingDevices = IotDevice::where('status', 'online')
            ->whereNotNull('vehicle_id')
            ->get();

        $idleCount = 0;
        foreach ($movingDevices as $device) {
            // Ambil telemetri terbaru
            $lastTelemetry = GpsTelemetry::where('device_id', $device->id)
                ->orderByDesc('gps_timestamp')
                ->first();

            if (!$lastTelemetry) continue;

            // Jika speed = 0 (atau sangat rendah) dan sudah > 15 menit
            $lastMovedAt = Carbon::parse($lastTelemetry->gps_timestamp);
            $isStoppedLong = $lastTelemetry->speed_kmh <= 2
                && $lastMovedAt->lt($idleThresh);

            if ($isStoppedLong) {
                $device->update(['status' => 'idle']);
                Vehicle::where('id', $device->vehicle_id)
                       ->update(['status' => 'idle']);
                $idleCount++;

                $this->line("  [idle] Device {$device->device_id} — speed={$lastTelemetry->speed_kmh} since {$lastMovedAt}");
            }
        }

        if ($idleCount > 0) {
            $this->info("Marked {$idleCount} device(s) as idle (speed=0 > 15 menit).");
        }

        // ── 3. Broadcast fleet status update ─────────────────────────────────
        if ($offlineDevices->count() > 0 || $idleCount > 0) {
            $this->broadcastFleetStatus();
        }

        $this->info('Done.');
    }

    private function broadcastFleetStatus(): void
    {
        try {
            $moving  = Vehicle::whereNull('deleted_at')->where('status', 'moving')->count();
            $idle    = Vehicle::whereNull('deleted_at')->where('status', 'idle')->count();
            $offline = Vehicle::whereNull('deleted_at')->where('status', 'offline')->count();
            $total   = Vehicle::whereNull('deleted_at')->count();

            broadcast(new \App\Events\FleetStatusUpdated([
                'moving'          => $moving,
                'idle'            => $idle,
                'offline'         => $offline,
                'total_vehicles'  => $total,
            ]));
        } catch (\Throwable $e) {
            $this->warn('Broadcast fleet status gagal: ' . $e->getMessage());
        }
    }
}