<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user = User::first();
});

test('TC-AUTH-01: halaman login dapat diakses', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
    $response->assertSee('Login');
})->group('auth');

test('TC-AUTH-02: login berhasil dengan kredensial valid', function () {
    // Ambil admin user yang passwordnya diketahui
    $admin = User::where('role', 'admin')->first()
          ?? User::first();

    // Reset password agar kita tahu passwordnya
    $admin->update(['password' => bcrypt('password123')]);

    $response = $this->post('/login', [
        'username' => $admin->username,
        'password' => 'password123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();
})->group('auth');

test('TC-AUTH-03: login gagal dengan password salah', function () {
    $response = $this->post('/login', [
        'username' => $this->user->username,
        'password' => 'passwordsalahbanget',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
})->group('auth');

test('TC-AUTH-04: login gagal dengan username tidak terdaftar', function () {
    $response = $this->post('/login', [
        'username' => 'useryangtidakadadidb_' . uniqid(),
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
})->group('auth');

test('TC-AUTH-05: halaman dashboard tidak bisa diakses tanpa login', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
})->group('auth');

test('TC-AUTH-06: logout berhasil', function () {
    $response = $this->actingAs($this->user)->post('/logout');
    $response->assertRedirect('/login');
    $this->assertGuest();
})->group('auth');

test('TC-AUTH-07: field username wajib diisi', function () {
    $response = $this->post('/login', [
        'username' => '',
        'password' => 'password123',
    ]);
    $response->assertSessionHasErrors();
})->group('auth');

test('TC-AUTH-08: password wajib diisi saat login', function () {
    $response = $this->post('/login', [
        'username' => $this->user->username,
        'password' => '',
    ]);
    $response->assertSessionHasErrors();
})->group('auth');