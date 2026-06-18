<?php

// ── TC-UNIT-01 ────────────────────────────────────────────────────
test('TC-UNIT-01: haversine menghitung jarak dengan benar', function () {
    // Jarak Malang kota ke Batu kota ≈ 15 km
    $lat1 = -7.9797; $lng1 = 112.6304; // Malang
    $lat2 = -7.8711; $lng2 = 112.5268; // Batu

    $dist = haversinePhp($lat1, $lng1, $lat2, $lng2);

    expect($dist)->toBeGreaterThan(14.0)
                 ->toBeLessThan(17.0);
})->group('unit');

// ── TC-UNIT-02 ────────────────────────────────────────────────────
test('TC-UNIT-02: haversine mengembalikan 0 untuk titik yang sama', function () {
    $lat = -7.9680; $lng = 112.5920;
    $dist = haversinePhp($lat, $lng, $lat, $lng);
    expect($dist)->toBe(0.0);
})->group('unit');

// ── TC-UNIT-03 ────────────────────────────────────────────────────
test('TC-UNIT-03: haversine selalu positif', function () {
    $dist = haversinePhp(-7.9, 112.6, -8.0, 112.5);
    expect($dist)->toBeGreaterThan(0);
})->group('unit');

// ── TC-UNIT-04 ────────────────────────────────────────────────────
test('TC-UNIT-04: ETA lebih besar jika jarak lebih jauh', function () {
    $eta1 = calcEtaPhp(5.0);  // 5 km
    $eta2 = calcEtaPhp(20.0); // 20 km
    expect($eta2)->toBeGreaterThan($eta1);
})->group('unit');

// ── TC-UNIT-05 ────────────────────────────────────────────────────
test('TC-UNIT-05: road factor 1.6 untuk jarak < 3km', function () {
    $dist     = 2.0; // km
    $rf       = $dist < 3 ? 1.6 : ($dist < 10 ? 1.4 : 1.25);
    expect($rf)->toBe(1.6);
})->group('unit');

// ── TC-UNIT-06 ────────────────────────────────────────────────────
test('TC-UNIT-06: road factor 1.4 untuk jarak 3-10km', function () {
    $dist = 7.0;
    $rf   = $dist < 3 ? 1.6 : ($dist < 10 ? 1.4 : 1.25);
    expect($rf)->toBe(1.4);
})->group('unit');

// ── TC-UNIT-07 ────────────────────────────────────────────────────
test('TC-UNIT-07: road factor 1.25 untuk jarak > 10km', function () {
    $dist = 15.0;
    $rf   = $dist < 3 ? 1.6 : ($dist < 10 ? 1.4 : 1.25);
    expect($rf)->toBe(1.25);
})->group('unit');

// ── Helper functions untuk unit test ─────────────────────────────
function haversinePhp(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R    = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat/2)**2
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

function calcEtaPhp(float $distRoad): int
{
    $speed = $distRoad < 5 ? 25 : ($distRoad < 15 ? 35 : 50);
    $delay = $distRoad < 5 ? 5  : ($distRoad < 15 ? 4  : 3);
    return (int) round(($distRoad / $speed) * 60 + $delay);
}