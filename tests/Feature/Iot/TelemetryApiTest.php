<?php

use App\Models\Vehicle;
use App\Models\IotDevice;
use App\Models\Trip;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Ambil device yang sudah ada di DB testing
    $this->device  = IotDevice::whereNotNull('vehicle_id')->first();
    $this->vehicle = $this->device?->vehicle;
});

test('TC-IOT-01: telemetry GPS valid diterima dan disimpan', function () {
    $response = $this->postJson('/api/telemetry', [
        'device_id'     => $this->device->device_id,
        'latitude'      => -7.9680,
        'longitude'     => 112.5920,
        'speed_kmh'     => 45.5,
        'heading'       => 90,
        'accuracy_m'    => 5.2,
        'network_type'  => '4G',
        'gps_timestamp' => now()->toISOString(),
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);

    $this->assertDatabaseHas('gps_telemetry', [
        'vehicle_id' => $this->vehicle->id,
    ]);
})->group('iot');

test('TC-IOT-02: status kendaraan berubah moving saat speed > 2', function () {
    $this->postJson('/api/telemetry', [
        'device_id'     => $this->device->device_id,
        'latitude'      => -7.9680,
        'longitude'     => 112.5920,
        'speed_kmh'     => 35.0,
        'gps_timestamp' => now()->toISOString(),
    ]);

    $this->assertDatabaseHas('vehicles', [
        'id'     => $this->vehicle->id,
        'status' => 'moving',
    ]);

    $this->assertDatabaseHas('iot_devices', [
        'device_id' => $this->device->device_id,
        'status'    => 'online',
    ]);
})->group('iot');

test('TC-IOT-03: status kendaraan berubah idle saat speed <= 2', function () {
    $this->postJson('/api/telemetry', [
        'device_id'     => $this->device->device_id,
        'latitude'      => -7.9680,
        'longitude'     => 112.5920,
        'speed_kmh'     => 0.0,
        'gps_timestamp' => now()->toISOString(),
    ]);

    $this->assertDatabaseHas('vehicles', [
        'id'     => $this->vehicle->id,
        'status' => 'idle',
    ]);
})->group('iot');

test('TC-IOT-04: telemetry dari device tidak dikenal mengembalikan 404', function () {
    $response = $this->postJson('/api/telemetry', [
        'device_id'     => 'DEVICE-TIDAK-ADA-SAMA-SEKALI',
        'latitude'      => -7.9680,
        'longitude'     => 112.5920,
        'speed_kmh'     => 30.0,
        'gps_timestamp' => now()->toISOString(),
    ]);

    $response->assertStatus(404);
    $response->assertJson(['error' => 'Device not found']);
})->group('iot');

test('TC-IOT-05: telemetry gagal tanpa latitude', function () {
    $response = $this->postJson('/api/telemetry', [
        'device_id' => $this->device->device_id,
        'longitude' => 112.5920,
        'speed_kmh' => 30.0,
    ]);
    $response->assertStatus(422);
})->group('iot');

test('TC-IOT-06: trip planned auto start saat kendaraan mulai bergerak', function () {
    // Pastikan tidak ada trip in_progress untuk vehicle ini dulu
    \App\Models\Trip::where('vehicle_id', $this->vehicle->id)
                    ->where('status', 'in_progress')
                    ->update(['status' => 'completed', 'arrived_at' => now()]);

    $trip = \App\Models\Trip::create([
        'trip_code'   => 'TRIP-IOT-' . uniqid(),
        'vehicle_id'  => $this->vehicle->id,
        'status'      => 'planned',
        'departed_at' => null,
        'origin_name' => 'Origin IOT Test',
        'origin_lat'  => -7.9680,
        'origin_lng'  => 112.5920,
        'dest_name'   => 'Dest IOT Test',
        'dest_lat'    => -8.0200,
        'dest_lng'    => 112.5100,
    ]);

    $this->postJson('/api/telemetry', [
        'device_id'     => $this->device->device_id,
        'latitude'      => -7.9680,
        'longitude'     => 112.5920,
        'speed_kmh'     => 25.0,
        'gps_timestamp' => now()->toISOString(),
    ]);

    $this->assertDatabaseHas('trips', [
        'id'     => $trip->id,
        'status' => 'in_progress',
    ]);
})->group('iot');

test('TC-IOT-07: trip auto complete saat kendaraan dalam radius 50m dari tujuan', function () {
    $trip = Trip::create([
        'trip_code'   => 'TRIP-IOT-COMP-' . uniqid(),
        'vehicle_id'  => $this->vehicle->id,
        'status'      => 'in_progress',
        'departed_at' => now()->subHour(),
        'origin_name' => 'Origin Test',
        'origin_lat'  => -7.9680,
        'origin_lng'  => 112.5920,
        'dest_name'   => 'Dest Test',
        'dest_lat'    => -7.9449367,
        'dest_lng'    => 112.6434517,
    ]);

    // Kirim posisi sangat dekat tujuan (< 50m)
    $this->postJson('/api/telemetry', [
        'device_id'     => $this->device->device_id,
        'latitude'      => -7.9449400,
        'longitude'     => 112.6434550,
        'speed_kmh'     => 3.0,
        'gps_timestamp' => now()->toISOString(),
    ]);

    $this->assertDatabaseHas('trips', [
        'id'     => $trip->id,
        'status' => 'completed',
    ]);
})->group('iot');

test('TC-IOT-08: last_latitude device terupdate setelah terima telemetry', function () {
    $this->postJson('/api/telemetry', [
        'device_id'     => $this->device->device_id,
        'latitude'      => -7.1111,
        'longitude'     => 112.2222,
        'speed_kmh'     => 20.0,
        'gps_timestamp' => now()->toISOString(),
    ]);

    $this->assertDatabaseHas('iot_devices', [
        'device_id'      => $this->device->device_id,
        'last_latitude'  => -7.1111,
        'last_longitude' => 112.2222,
    ]);
})->group('iot');