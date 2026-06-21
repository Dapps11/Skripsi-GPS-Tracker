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
    protected $description = 'Mark devices offline jika tidak ada heartbeat > 10 menit, alert jika berhenti > 10 menit saat trip aktif';

    public function handle(): void
    {
        $now            = Carbon::now();
        $offlineThresh  = $now->copy()->subMinutes(10);  // heartbeat > 10 menit → offline
        $idleThresh     = $now->copy()->subMinutes(10);  // speed=0 > 10 menit → idle + alert jika trip aktif

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

        // ── 2. Moving → Idle (speed = 0 selama > 10 menit) ──────────────────
        // Cek device yang masih 'online' (moving) tapi telemetri terakhirnya speed=0 > 10 menit
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

            // Jika speed = 0 (atau sangat rendah) dan sudah > 10 menit
            $lastMovedAt = Carbon::parse($lastTelemetry->gps_timestamp);
            $isStoppedLong = $lastTelemetry->speed_kmh <= 2
                && $lastMovedAt->lt($idleThresh);

            if ($isStoppedLong) {
                $device->update(['status' => 'idle']);
                Vehicle::where('id', $device->vehicle_id)
                       ->update(['status' => 'idle']);
                $idleCount++;

                // Cek apakah kendaraan ini sedang dalam trip in_progress
                // Jika ya → buat alert "kendaraan berhenti terlalu lama"
                $activeTrip = \App\Models\Trip::where('vehicle_id', $device->vehicle_id)
                    ->where('status', 'in_progress')
                    ->first();

                if ($activeTrip) {
                    // Cek apakah alert serupa sudah dibuat dalam 30 menit terakhir (hindari spam)
                    $recentAlert = \App\Models\Alert::where('vehicle_id', $device->vehicle_id)
                        ->where('alert_type', 'vehicle_stopped')
                        ->where('created_at', '>=', Carbon::now()->subMinutes(30))
                        ->exists();

                    if (!$recentAlert) {
                        $stopDuration = (int) $lastMovedAt->diffInMinutes(Carbon::now());
                        $vehicle = Vehicle::find($device->vehicle_id);

                        \App\Models\Alert::create([
                            'alert_type'   => 'vehicle_stopped',
                            'severity'     => 'warning',
                            'vehicle_id'   => $device->vehicle_id,
                            'driver_id'    => $device->driver_id,
                            'device_id'    => $device->id,
                            'trip_id'      => $activeTrip->id,
                            'title'        => 'Kendaraan Berhenti Terlalu Lama — ' . optional($vehicle)->name,
                            'message'      => "Trip {$activeTrip->trip_code} sedang berjalan, tapi kendaraan sudah berhenti selama {$stopDuration} menit (speed = 0).",
                            'triggered_at' => now(),
                        ]);

                        $this->line("  [alert] Vehicle {$device->vehicle_id} berhenti {$stopDuration} menit saat trip in_progress");
                    }
                }

                $this->line("  [idle] Device {$device->device_id} — speed={$lastTelemetry->speed_kmh} since {$lastMovedAt}");
            }
        }

        if ($idleCount > 0) {
            $this->info("Marked {$idleCount} device(s) as idle (speed=0 > 10 menit).");
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