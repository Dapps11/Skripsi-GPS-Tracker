<?php

use App\Models\IotDevice;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

test('TC-STATUS-DB-01: device offline setelah 10 menit tanpa heartbeat', function () {
    // Update device yang ada jadi idle dengan heartbeat lama
    $device = IotDevice::first();
    $device->update([
        'status'         => 'idle',
        'last_heartbeat' => now()->subMinutes(11),
    ]);

    $this->artisan('devices:mark-offline');

    $this->assertDatabaseHas('iot_devices', [
        'id'     => $device->id,
        'status' => 'offline',
    ]);
})->group('status-db');

test('TC-STATUS-DB-02: device TIDAK offline jika heartbeat < 10 menit', function () {
    $device = IotDevice::first();
    $device->update([
        'status'         => 'idle',
        'last_heartbeat' => now()->subMinutes(5),
    ]);

    $this->artisan('devices:mark-offline');

    $this->assertDatabaseHas('iot_devices', [
        'id'     => $device->id,
        'status' => 'idle',
    ]);
})->group('status-db');

test('TC-STATUS-DB-03: vehicle offline saat device offline', function () {
    $device  = IotDevice::whereNotNull('vehicle_id')->first();
    $vehicle = $device->vehicle;

    $device->update([
        'status'         => 'idle',
        'last_heartbeat' => now()->subMinutes(15),
    ]);
    $vehicle->update(['status' => 'idle']);

    $this->artisan('devices:mark-offline');

    $this->assertDatabaseHas('vehicles', [
        'id'     => $vehicle->id,
        'status' => 'offline',
    ]);
})->group('status-db');