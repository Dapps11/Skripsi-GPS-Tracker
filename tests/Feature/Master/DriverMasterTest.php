<?php

use App\Models\User;
use App\Models\Driver;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user = User::first();
});

test('TC-DRV-01: halaman daftar driver dapat diakses', function () {
    $response = $this->actingAs($this->user)->get('/master/drivers');
    $response->assertStatus(200);
})->group('driver');

test('TC-DRV-02: halaman tambah driver dapat diakses', function () {
    $response = $this->actingAs($this->user)->get('/master/drivers/create');
    $response->assertStatus(200);
})->group('driver');

test('TC-DRV-03: driver baru berhasil ditambahkan dengan data valid', function () {
    $code = 'DRV-TEST-' . uniqid();
    $response = $this->actingAs($this->user)->post('/master/drivers', [
        'driver_code'  => $code,
        'full_name'    => 'Budi Santoso Test',
        'phone_number' => '08123456789',
        'license_no'   => 'SIM' . uniqid(),
        'status'       => 'available',
    ]);

    $response->assertRedirect('/master/drivers');
    $this->assertDatabaseHas('drivers', [
        'driver_code' => $code,
        'full_name'   => 'Budi Santoso Test',
    ]);
})->group('driver');

test('TC-DRV-04: driver gagal ditambahkan tanpa full_name', function () {
    $response = $this->actingAs($this->user)->post('/master/drivers', [
        'driver_code' => 'DRV-NO-NAME-' . uniqid(),
        'full_name'   => '',
        'status'      => 'available',
    ]);
    $response->assertSessionHasErrors('full_name');
})->group('driver');

test('TC-DRV-05: driver gagal ditambahkan dengan driver_code duplikat', function () {
    $existing = Driver::first();

    $response = $this->actingAs($this->user)->post('/master/drivers', [
        'driver_code' => $existing->driver_code, // duplikat
        'full_name'   => 'Nama Lain',
        'status'      => 'available',
    ]);
    $response->assertSessionHasErrors('driver_code');
})->group('driver');

test('TC-DRV-06: halaman edit driver dapat diakses', function () {
    $driver = Driver::first();
    $response = $this->actingAs($this->user)
                     ->get("/master/drivers/{$driver->id}/edit");
    $response->assertStatus(200);
    $response->assertSee($driver->full_name);
})->group('driver');

test('TC-DRV-07: data driver berhasil diperbarui', function () {
    // Buat driver baru agar tidak ganggu data asli
    $driver = Driver::create([
        'driver_code'  => 'DRV-UPD-' . uniqid(),
        'full_name'    => 'Nama Sebelum Update',
        'phone_number' => '08111111111',
        'status'       => 'available',
    ]);

    $response = $this->actingAs($this->user)->put("/master/drivers/{$driver->id}", [
        'driver_code' => $driver->driver_code,
        'full_name'   => 'Nama Sesudah Update',
        'status'      => 'available',
    ]);

    $response->assertRedirect('/master/drivers');
    $this->assertDatabaseHas('drivers', [
        'id'        => $driver->id,
        'full_name' => 'Nama Sesudah Update',
    ]);
})->group('driver');

test('TC-DRV-08: driver berhasil dihapus (soft delete)', function () {
    $driver = Driver::create([
        'driver_code' => 'DRV-DEL-' . uniqid(),
        'full_name'   => 'Driver Untuk Dihapus',
        'status'      => 'available',
    ]);

    $response = $this->actingAs($this->user)->delete("/master/drivers/{$driver->id}");
    $response->assertRedirect('/master/drivers');
    $this->assertSoftDeleted('drivers', ['id' => $driver->id]);
})->group('driver');

test('TC-DRV-09: daftar driver menampilkan data yang ada', function () {
    $driver = Driver::first();
    $response = $this->actingAs($this->user)->get('/master/drivers');
    $response->assertSee($driver->full_name);
})->group('driver');

test('TC-DRV-10: halaman master driver tidak bisa diakses tanpa login', function () {
    $response = $this->get('/master/drivers');
    $response->assertRedirect('/login');
})->group('driver');