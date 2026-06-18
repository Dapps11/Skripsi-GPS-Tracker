<?php

use App\Models\IotDevice;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->device = IotDevice::whereNotNull('vehicle_id')->first();
});

test('TC-DROWSY-01: data drowsy normal berhasil disimpan', function () {
    $response = $this->postJson('/api/drowsy', [
        'device_id'     => $this->device->device_id,
        'event_type'    => 'normal',
        'confidence'    => 0.95,
        'driver_status' => 'normal',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);

    $this->assertDatabaseHas('driver_monitoring_events', [
        'vehicle_id'    => $this->device->vehicle_id,
        'event_type'    => 'normal',
        'driver_status' => 'normal',
    ]);
})->group('drowsy');

test('TC-DROWSY-02: event drowsy_warning membuat alert warning', function () {
    $this->postJson('/api/drowsy', [
        'device_id'     => $this->device->device_id,
        'event_type'    => 'drowsy_warning',
        'confidence'    => 0.82,
        'driver_status' => 'warning',
    ]);

    $this->assertDatabaseHas('alerts', [
        'vehicle_id' => $this->device->vehicle_id,
        'alert_type' => 'drowsy_driver',
        'severity'   => 'warning',
    ]);
})->group('drowsy');

test('TC-DROWSY-03: event eyes_closed dengan status danger membuat alert critical', function () {
    $this->postJson('/api/drowsy', [
        'device_id'     => $this->device->device_id,
        'event_type'    => 'eyes_closed',
        'confidence'    => 0.97,
        'driver_status' => 'danger',
    ]);

    $this->assertDatabaseHas('alerts', [
        'vehicle_id' => $this->device->vehicle_id,
        'alert_type' => 'drowsy_driver',
        'severity'   => 'critical',
    ]);
})->group('drowsy');

test('TC-DROWSY-04: drowsy dari device tidak dikenal mengembalikan 404', function () {
    $response = $this->postJson('/api/drowsy', [
        'device_id'     => 'DEVICE-TIDAK-ADA-SAMA-SEKALI',
        'event_type'    => 'normal',
        'confidence'    => 0.9,
        'driver_status' => 'normal',
    ]);

    $response->assertStatus(404);
    $response->assertJson(['error' => 'Device not found']);
})->group('drowsy');

test('TC-DROWSY-05: drowsy gagal tanpa driver_status', function () {
    $response = $this->postJson('/api/drowsy', [
        'device_id'  => $this->device->device_id,
        'event_type' => 'normal',
        'confidence' => 0.9,
    ]);
    $response->assertStatus(422);
})->group('drowsy');

test('TC-DROWSY-06: driver_status normal tidak membuat alert', function () {
    // Hitung alert sebelum
    $countBefore = \App\Models\Alert::where('vehicle_id', $this->device->vehicle_id)
                                    ->where('alert_type', 'drowsy_driver')
                                    ->count();

    $this->postJson('/api/drowsy', [
        'device_id'     => $this->device->device_id,
        'event_type'    => 'normal',
        'confidence'    => 0.99,
        'driver_status' => 'normal',
    ]);

    $countAfter = \App\Models\Alert::where('vehicle_id', $this->device->vehicle_id)
                                   ->where('alert_type', 'drowsy_driver')
                                   ->count();

    expect($countAfter)->toBe($countBefore); // tidak bertambah
})->group('drowsy');

test('TC-DROWSY-07: event yawning tersimpan dengan benar', function () {
    $response = $this->postJson('/api/drowsy', [
        'device_id'     => $this->device->device_id,
        'event_type'    => 'yawning',
        'confidence'    => 0.75,
        'driver_status' => 'warning',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('driver_monitoring_events', [
        'vehicle_id' => $this->device->vehicle_id,
        'event_type' => 'yawning',
    ]);
})->group('drowsy');

test('TC-DROWSY-08: confidence disimpan dengan benar di database', function () {
    $this->postJson('/api/drowsy', [
        'device_id'     => $this->device->device_id,
        'event_type'    => 'distracted',
        'confidence'    => 0.88,
        'driver_status' => 'warning',
    ]);

    // Query langsung tanpa orderBy — cari berdasarkan event_type
    $event = \App\Models\DriverMonitoringEvent::where('vehicle_id', $this->device->vehicle_id)
                                              ->where('event_type', 'distracted')
                                              ->orderByDesc('recorded_at') // pakai kolom yang ada
                                              ->first();

    expect($event)->not->toBeNull();
    expect((float) $event->confidence)->toBeGreaterThan(0.87)
                                      ->toBeLessThan(0.89);
})->group('drowsy');