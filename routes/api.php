<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IoTApiController;

/*
|--------------------------------------------------------------------------
| IoT API Routes
|--------------------------------------------------------------------------
| Semua endpoint IoT dilindungi middleware iot.auth yang memverifikasi
| X-Device-Key header. Set IOT_API_KEY di .env.
|
| Contoh request dari SIM7600:
|   curl -X POST https://domain.com/api/telemetry \
|        -H "X-Device-Key: <IOT_API_KEY>" \
|        -H "Content-Type: application/json" \
|        -d '{"device_id":"TRACKER-10","latitude":-7.96,"longitude":112.59,"speed_kmh":30}'
*/

Route::middleware('iot.auth')->group(function () {
    // SIM7600 — kirim data GPS
    Route::post('/telemetry', [IoTApiController::class, 'receiveTelemetry']);

    // OpenMV — kirim event kantuk
    Route::post('/drowsy', [IoTApiController::class, 'receiveDrowsy']);
});

// Fallback GET telemetry — tidak perlu auth, hanya info
Route::get('/telemetry', fn() => response()->json([
    'error'   => 'Method not allowed. Use POST with X-Device-Key header.',
    'example' => [
        'device_id'     => 'TRACKER-001',
        'latitude'      => -7.9680,
        'longitude'     => 112.5920,
        'speed_kmh'     => 0,
        'gps_timestamp' => now()->toISOString(),
    ],
], 405));