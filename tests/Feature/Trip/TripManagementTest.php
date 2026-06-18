<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user    = User::first();
    $this->vehicle = Vehicle::first();
    $this->driver  = Driver::first();
});

test('TC-TRIP-01: halaman daftar trip dapat diakses', function () {
    $response = $this->actingAs($this->user)->get('/trips');
    $response->assertStatus(200);
    $response->assertSee('Trip Management');
})->group('trip');

test('TC-TRIP-02: halaman create trip dapat diakses', function () {
    $response = $this->actingAs($this->user)->get('/trips/create');
    $response->assertStatus(200);
})->group('trip');

test('TC-TRIP-03: trip berhasil dibuat dengan data valid', function () {
    $response = $this->actingAs($this->user)->post('/trips', [
        'vehicle_id'           => $this->vehicle->id,
        'driver_id'            => $this->driver->id,
        'origin_name'          => 'Gudang Utama Greenfields Test',
        'origin_address'       => 'Jl. Raya Batu No. 1, Malang',
        'origin_lat'           => -7.9680,
        'origin_lng'           => 112.5920,
        'dest_name'            => 'Gudang Tujuan Test',
        'dest_address'         => 'Jl. Ngajum No. 5, Malang',
        'dest_lat'             => -8.0200,
        'dest_lng'             => 112.5100,
        'estimated_arrival_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
        'notes'                => 'Test trip automated',
    ]);

    $response->assertRedirect('/trips');
    $this->assertDatabaseHas('trips', [
        'vehicle_id'  => $this->vehicle->id,
        'origin_name' => 'Gudang Utama Greenfields Test',
        'status'      => 'planned',
    ]);
})->group('trip');

test('TC-TRIP-04: trip gagal dibuat tanpa vehicle', function () {
    $response = $this->actingAs($this->user)->post('/trips', [
        'vehicle_id'  => '',
        'origin_name' => 'Gudang Utama',
        'origin_lat'  => -7.9680,
        'origin_lng'  => 112.5920,
        'dest_name'   => 'Tujuan',
        'dest_lat'    => -8.0200,
        'dest_lng'    => 112.5100,
    ]);
    $response->assertSessionHasErrors('vehicle_id');
})->group('trip');

test('TC-TRIP-05: trip gagal dibuat tanpa koordinat origin', function () {
    $response = $this->actingAs($this->user)->post('/trips', [
        'vehicle_id'  => $this->vehicle->id,
        'origin_name' => 'Gudang Utama',
        'origin_lat'  => '',
        'origin_lng'  => '',
        'dest_name'   => 'Tujuan',
        'dest_lat'    => -8.0200,
        'dest_lng'    => 112.5100,
    ]);
    $response->assertSessionHasErrors('origin_lat');
})->group('trip');

test('TC-TRIP-06: trip berhasil dimulai (planned → in_progress)', function () {
    // Buat trip planned baru
    $trip = Trip::create([
        'trip_code'   => 'TRIP-TEST-' . uniqid(),
        'vehicle_id'  => $this->vehicle->id,
        'driver_id'   => $this->driver?->id,
        'origin_name' => 'Origin Test',
        'origin_lat'  => -7.9680,
        'origin_lng'  => 112.5920,
        'dest_name'   => 'Dest Test',
        'dest_lat'    => -8.0200,
        'dest_lng'    => 112.5100,
        'status'      => 'planned',
    ]);

    $response = $this->actingAs($this->user)
                     ->post("/trips/{$trip->id}/start");

    $response->assertRedirect();
    $this->assertDatabaseHas('trips', [
        'id'     => $trip->id,
        'status' => 'in_progress',
    ]);
})->group('trip');

test('TC-TRIP-07: trip berhasil diselesaikan (in_progress → completed)', function () {
    $trip = Trip::create([
        'trip_code'   => 'TRIP-TEST-' . uniqid(),
        'vehicle_id'  => $this->vehicle->id,
        'origin_name' => 'Origin Test',
        'origin_lat'  => -7.9680,
        'origin_lng'  => 112.5920,
        'dest_name'   => 'Dest Test',
        'dest_lat'    => -8.0200,
        'dest_lng'    => 112.5100,
        'status'      => 'in_progress',
        'departed_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($this->user)
                     ->post("/trips/{$trip->id}/complete");

    $response->assertRedirect();
    $this->assertDatabaseHas('trips', [
        'id'     => $trip->id,
        'status' => 'completed',
    ]);
})->group('trip');

test('TC-TRIP-08: trip in_progress tidak bisa dihapus', function () {
    $trip = Trip::create([
        'trip_code'   => 'TRIP-TEST-' . uniqid(),
        'vehicle_id'  => $this->vehicle->id,
        'origin_name' => 'Origin Test',
        'origin_lat'  => -7.9680,
        'origin_lng'  => 112.5920,
        'dest_name'   => 'Dest Test',
        'dest_lat'    => -8.0200,
        'dest_lng'    => 112.5100,
        'status'      => 'in_progress',
        'departed_at' => now()->subHour(),
    ]);

    $this->actingAs($this->user)->delete("/trips/{$trip->id}");
    $this->assertDatabaseHas('trips', ['id' => $trip->id]);
})->group('trip');

test('TC-TRIP-09: trip planned dapat dihapus', function () {
    $trip = Trip::create([
        'trip_code'   => 'TRIP-TEST-' . uniqid(),
        'vehicle_id'  => $this->vehicle->id,
        'origin_name' => 'Origin Test',
        'origin_lat'  => -7.9680,
        'origin_lng'  => 112.5920,
        'dest_name'   => 'Dest Test',
        'dest_lat'    => -8.0200,
        'dest_lng'    => 112.5100,
        'status'      => 'planned',
    ]);

    $this->actingAs($this->user)->delete("/trips/{$trip->id}");
    $this->assertSoftDeleted('trips', ['id' => $trip->id]);
})->group('trip');

test('TC-TRIP-10: halaman detail trip dapat diakses', function () {
    // Ambil trip completed yang sudah ada
    $trip = Trip::where('status', 'completed')->first()
         ?? Trip::first();

    if (!$trip) {
        $this->markTestSkipped('Tidak ada data trip di database testing.');
    }

    $response = $this->actingAs($this->user)->get("/trips/{$trip->id}");
    $response->assertStatus(200);
})->group('trip');