<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\IotDevice;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user    = User::first();
    $this->vehicle = Vehicle::first();
    $this->driver  = Driver::first();
});

test('TC-DEV-01: halaman daftar device dapat diakses', function () {
    $response = $this->actingAs($this->user)->get('/devices');
    $response->assertStatus(200);
})->group('device');

test('TC-DEV-02: device baru berhasil ditambahkan', function () {
    $response = $this->actingAs($this->user)->post('/devices', [
        'device_id'  => 'TRACKER-TEST-' . uniqid(),
        'vehicle_id' => $this->vehicle->id,
        'driver_id'  => $this->driver?->id,
    ]);

    $response->assertRedirect('/devices');
})->group('device');

test('TC-DEV-03: device gagal dibuat dengan device_id duplikat', function () {
    $existing = IotDevice::first();

    $response = $this->actingAs($this->user)->post('/devices', [
        'device_id'  => $existing->device_id, // duplikat
        'vehicle_id' => $this->vehicle->id,
    ]);

    $response->assertSessionHasErrors('device_id');
})->group('device');

test('TC-DEV-04: device dapat dihapus', function () {
    // Buat device baru dulu agar tidak hapus data asli
    $device = IotDevice::create([
        'device_id'   => 'TRACKER-DEL-' . uniqid(),
        'vehicle_id'  => null,
        'device_type' => 'tracker',
        'status'      => 'offline',
    ]);

    $response = $this->actingAs($this->user)->delete("/devices/{$device->id}");
    $response->assertRedirect('/devices');
    $this->assertSoftDeleted('iot_devices', ['id' => $device->id]);
})->group('device');