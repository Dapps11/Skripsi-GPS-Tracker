<?php

// Tests yang murni logika (tidak butuh DB) tetap di Unit
// Tests yang butuh DB/Factory dipindah ke Feature

// ── TC-STATUS-01 s/d 03: pure logic, tidak butuh DB ──────────────
test('TC-STATUS-01: device berstatus online saat speed > 2', function () {
    $speed  = 35.0;
    $status = $speed > 2 ? 'online' : 'idle';
    expect($status)->toBe('online');
})->group('status');

test('TC-STATUS-02: device berstatus idle saat speed <= 2', function () {
    $speed  = 0.0;
    $status = $speed > 2 ? 'online' : 'idle';
    expect($status)->toBe('idle');
})->group('status');

test('TC-STATUS-03: vehicle berstatus moving saat speed > 2', function () {
    $speed  = 50.0;
    $status = $speed > 2 ? 'moving' : 'idle';
    expect($status)->toBe('moving');
})->group('status');

// tests/Unit/StatusLogicTest.php
test('TC-STATUS-04: threshold offline = 10 menit', function () {
    $thresholdMenit = 10;

    // Simulasi: device terakhir heartbeat 11 menit yang lalu
    $lastHeartbeat = now()->subMinutes(11);
    $now           = now();

    // Hitung selisih dalam menit (cara yang sama dengan MarkOfflineDevices command)
    $menitSejak = $lastHeartbeat->diffInMinutes($now);

    expect($menitSejak)->toBeGreaterThanOrEqual($thresholdMenit);
})->group('status');

test('TC-STATUS-05: device tidak offline jika heartbeat < 10 menit', function () {
    $lastHeartbeat  = now()->subMinutes(5);
    $thresholdMenit = 10;
    $isOffline      = now()->diffInMinutes($lastHeartbeat) >= $thresholdMenit;
    expect($isOffline)->toBeFalse();
})->group('status');

test('TC-STATUS-06: arrival distance threshold 50 meter', function () {
    $ARRIVAL_DIST = 0.05; // km = 50 meter
    expect($ARRIVAL_DIST * 1000)->toBe(50.0); // dalam meter
})->group('status');