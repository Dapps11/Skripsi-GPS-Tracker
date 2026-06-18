<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IoTApiController;

// Endpoint untuk SIM7600 kirim data GPS
Route::post('/telemetry', [IoTApiController::class, 'receiveTelemetry']);

// Endpoint untuk OpenMV kirim event kantuk
Route::post('/drowsy', [IoTApiController::class, 'receiveDrowsy']);

// Test endpoint
Route::get('/test', function () {
    return response()->json([
        'status' => 'ok'
    ]);
});

// Fallback GET telemetry
Route::get('/telemetry', function () {
    return response()->json([
        'error'   => 'Method not allowed. Use POST.',
        'example' => [
            'device_id'     => 'TRACKER-001',
            'latitude'      => -7.9680,
            'longitude'     => 112.5920,
            'speed_kmh'     => 0,
            'gps_timestamp' => now()->toISOString(),
        ]
    ], 405);
});

Route::post('/sim-test', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'success' => true,
        'received' => $request->all(),
        'raw' => $request->getContent(),
    ]);
});

