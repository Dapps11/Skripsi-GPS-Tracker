<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user = User::first();
});

test('TC-VEH-01: halaman daftar kendaraan dapat diakses', function () {
    $response = $this->actingAs($this->user)->get('/master/vehicles');
    $response->assertStatus(200);
})->group('vehicle');

test('TC-VEH-02: halaman tambah kendaraan dapat diakses', function () {
    $response = $this->actingAs($this->user)->get('/master/vehicles/create');
    $response->assertStatus(200);
})->group('vehicle');

test('TC-VEH-03: kendaraan baru berhasil ditambahkan dengan data valid', function () {
    $code  = 'GF-TEST-' . uniqid();
    $plate = 'N-TST-' . rand(100, 999);

    $response = $this->actingAs($this->user)->post('/master/vehicles', [
        'vehicle_code'  => $code,
        'name'          => 'Truck Test Automated',
        'license_plate' => $plate,
        'vehicle_type'  => 'Truk Susu',
        'brand'         => 'Mitsubishi',
        'model'         => 'Canter',
        'year'          => 2022,
        'status'        => 'offline',
    ]);

    $response->assertRedirect('/master/vehicles');
    $this->assertDatabaseHas('vehicles', [
        'vehicle_code'  => $code,
        'license_plate' => $plate,
    ]);
})->group('vehicle');

test('TC-VEH-04: kendaraan gagal ditambahkan tanpa nama', function () {
    $response = $this->actingAs($this->user)->post('/master/vehicles', [
        'vehicle_code'  => 'GF-NONAME-' . uniqid(),
        'name'          => '',
        'license_plate' => 'N-NN-' . rand(100, 999),
        'vehicle_type'  => 'Truk Susu',
    ]);
    $response->assertSessionHasErrors('name');
})->group('vehicle');

test('TC-VEH-05: kendaraan gagal ditambahkan dengan license_plate duplikat', function () {
    $existing = Vehicle::first();

    $response = $this->actingAs($this->user)->post('/master/vehicles', [
        'vehicle_code'  => 'GF-DUP-' . uniqid(),
        'name'          => 'Truck Duplikat',
        'license_plate' => $existing->license_plate, // duplikat
        'vehicle_type'  => 'Truk Susu',
    ]);
    $response->assertSessionHasErrors('license_plate');
})->group('vehicle');

test('TC-VEH-06: halaman edit kendaraan dapat diakses', function () {
    $vehicle = Vehicle::first();
    $response = $this->actingAs($this->user)
                     ->get("/master/vehicles/{$vehicle->id}/edit");
    $response->assertStatus(200);
    $response->assertSee($vehicle->name);
})->group('vehicle');

test('TC-VEH-07: data kendaraan berhasil diperbarui', function () {
    $vehicle = Vehicle::create([
        'vehicle_code'  => 'GF-UPD-' . uniqid(),
        'name'          => 'Nama Sebelum Update',
        'license_plate' => 'N-UPD-' . rand(100, 999),
        'vehicle_type'  => 'Truk Susu',
        'status'        => 'offline',
    ]);

    $response = $this->actingAs($this->user)->put("/master/vehicles/{$vehicle->id}", [
        'vehicle_code'  => $vehicle->vehicle_code,
        'name'          => 'Nama Sesudah Update',
        'license_plate' => $vehicle->license_plate,
        'vehicle_type'  => 'Truk Susu',
        'brand'         => 'Isuzu',
        'model'         => 'Elf',
        'year'          => 2023,
        'status'        => 'offline',
    ]);

    $response->assertRedirect('/master/vehicles');
    $this->assertDatabaseHas('vehicles', [
        'id'    => $vehicle->id,
        'name'  => 'Nama Sesudah Update',
        'brand' => 'Isuzu',
    ]);
})->group('vehicle');

test('TC-VEH-08: kendaraan berhasil dihapus (soft delete)', function () {
    $vehicle = Vehicle::create([
        'vehicle_code'  => 'GF-DEL-' . uniqid(),
        'name'          => 'Kendaraan Untuk Dihapus',
        'license_plate' => 'N-DEL-' . rand(100, 999),
        'vehicle_type'  => 'Truk Susu',
        'status'        => 'offline',
    ]);

    $response = $this->actingAs($this->user)->delete("/master/vehicles/{$vehicle->id}");
    $response->assertRedirect('/master/vehicles');
    $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
})->group('vehicle');

test('TC-VEH-09: daftar kendaraan menampilkan data yang ada', function () {
    $vehicle  = Vehicle::first();
    $response = $this->actingAs($this->user)->get('/master/vehicles');
    $response->assertSee($vehicle->name);
})->group('vehicle');

test('TC-VEH-10: halaman master kendaraan tidak bisa diakses tanpa login', function () {
    $response = $this->get('/master/vehicles');
    $response->assertRedirect('/login');
})->group('vehicle');

test('TC-VEH-11: field brand model year tersimpan dengan benar', function () {
    $plate = 'N-BMY-' . rand(100, 999);

    $this->actingAs($this->user)->post('/master/vehicles', [
        'vehicle_code'  => 'GF-BMY-' . uniqid(),
        'name'          => 'Truck Brand Test',
        'license_plate' => $plate,
        'vehicle_type'  => 'Truk Susu',
        'brand'         => 'Isuzu',
        'model'         => 'Elf',
        'year'          => 2021,
        'status'        => 'offline',
    ]);

    $this->assertDatabaseHas('vehicles', [
        'license_plate' => $plate,
        'brand'         => 'Isuzu',
        'model'         => 'Elf',
        'year'          => 2021,
    ]);
})->group('vehicle');

test('TC-VEH-12: tahun kendaraan harus antara 2000 dan 2030', function () {
    $response = $this->actingAs($this->user)->post('/master/vehicles', [
        'vehicle_code'  => 'GF-YR-' . uniqid(),
        'name'          => 'Truck Year Test',
        'license_plate' => 'N-YR-' . rand(100, 999),
        'vehicle_type'  => 'Truk Susu',
        'year'          => 1990,
        'status'        => 'offline',
    ]);
    $response->assertSessionHasErrors('year');
})->group('vehicle');