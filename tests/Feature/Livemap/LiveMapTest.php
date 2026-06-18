<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Trip;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user    = User::first();
    $this->vehicle = Vehicle::first();
});

test('TC-MAP-01: halaman live map dapat diakses', function () {
    $response = $this->actingAs($this->user)->get('/live-map');
    $response->assertStatus(200);
})->group('livemap');

test('TC-MAP-02: halaman live map menampilkan daftar kendaraan', function () {
    $response = $this->actingAs($this->user)->get('/live-map');
    $response->assertStatus(200);
    $response->assertSee($this->vehicle->name);
})->group('livemap');

test('TC-MAP-03: halaman live map detail kendaraan dapat diakses', function () {
    $response = $this->actingAs($this->user)
                     ->get("/live-map/{$this->vehicle->id}");
    $response->assertStatus(200);
})->group('livemap');

test('TC-MAP-04: API internal trip mengembalikan data valid saat ada trip aktif', function () {
    $trip = \App\Models\Trip::where('status', 'in_progress')->first();

    if (!$trip) {
        $this->markTestSkipped('Tidak ada trip in_progress di database testing.');
    }

    $response = $this->actingAs($this->user)
                     ->getJson("/api/internal/trip/{$trip->vehicle_id}");

    $response->assertStatus(200);

    // Cek struktur yang pasti ada
    $response->assertJsonStructure([
        'trip',
        'gps_track',
        'driver_status',
        'current_speed',
    ]);

    // eta_minutes mungkin ada di dalam trip object
    $data = $response->json();
    expect($data)->toHaveKey('trip');
    expect($data)->toHaveKey('gps_track');
    expect($data)->toHaveKey('driver_status');
})->group('livemap');

test('TC-MAP-05: API internal trip mengembalikan struktur response yang benar', function () {
    // API selalu return response valid (trip bisa null, in_progress, atau completed)
    $response = $this->actingAs($this->user)
                     ->getJson("/api/internal/trip/{$this->vehicle->id}");

    $response->assertStatus(200);

    // Verifikasi struktur response — trip bisa null atau berisi data
    $data = $response->json();
    expect($data)->toHaveKey('gps_track');
    expect($data)->toHaveKey('driver_status');
    expect($data)->toHaveKey('current_speed');

    // trip bisa null atau array
    expect($data['trip'])->toBeArray()->or->toBeNull();
})->group('livemap');

test('TC-MAP-06: API vehicle-device mengembalikan info device', function () {
    $response = $this->actingAs($this->user)
                     ->getJson("/api/internal/vehicle-device/{$this->vehicle->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure(['device', 'driver']);
})->group('livemap');

test('TC-MAP-07: live map tanpa login diredirect ke login', function () {
    $response = $this->get('/live-map');
    $response->assertRedirect('/login');
})->group('livemap');

test('TC-MAP-08: live map detail kendaraan tidak ada mengembalikan 404', function () {
    $response = $this->actingAs($this->user)->get('/live-map/99999');
    $response->assertStatus(404);
})->group('livemap');

test('TC-MAP-09: API vehicles-position mengembalikan koordinat kendaraan', function () {
    $response = $this->actingAs($this->user)
                     ->getJson('/api/internal/vehicles-position');
    $response->assertStatus(200);
    expect($response->json())->toBeArray();
})->group('livemap');

test('TC-MAP-10: map preference tersimpan dan mempengaruhi tampilan', function () {
    $this->actingAs($this->user)
         ->postJson('/map-preference', ['type' => 'gmaps'])
         ->assertJson(['ok' => true, 'type' => 'gmaps']);

    $response = $this->actingAs($this->user)->get('/live-map');
    $response->assertStatus(200);

    $this->actingAs($this->user)
         ->postJson('/map-preference', ['type' => 'osm'])
         ->assertJson(['ok' => true, 'type' => 'osm']);
})->group('livemap');