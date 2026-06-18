<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user = User::first();
});

test('TC-DASH-01: dashboard dapat diakses oleh user login', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');
    $response->assertStatus(200);
})->group('dashboard');

test('TC-DASH-02: dashboard menampilkan komponen fleet summary', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');
    $response->assertStatus(200);
    $response->assertSee('Moving');
    $response->assertSee('Idle');
    $response->assertSee('Offline');
})->group('dashboard');

test('TC-DASH-03: API fleet summary mengembalikan data valid', function () {
    $response = $this->actingAs($this->user)
                     ->getJson('/api/internal/fleet-summary');
    $response->assertStatus(200);
    $response->assertJsonStructure(['moving', 'idle', 'offline']);
})->group('dashboard');

test('TC-DASH-04: API vehicles position mengembalikan array', function () {
    $response = $this->actingAs($this->user)
                     ->getJson('/api/internal/vehicles-position');
    $response->assertStatus(200);
    expect($response->json())->toBeArray();
})->group('dashboard');

test('TC-DASH-05: map preference dapat disimpan ke session (osm)', function () {
    $response = $this->actingAs($this->user)
                     ->postJson('/map-preference', ['type' => 'osm']);
    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'type' => 'osm']);
})->group('dashboard');

test('TC-DASH-06: map preference dapat disimpan ke session (gmaps)', function () {
    $response = $this->actingAs($this->user)
                     ->postJson('/map-preference', ['type' => 'gmaps']);
    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'type' => 'gmaps']);
})->group('dashboard');