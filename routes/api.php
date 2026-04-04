<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IoTApiController;

// Endpoint untuk SIM7600 kirim data GPS
Route::post('/telemetry', [IoTApiController::class, 'receiveTelemetry']);

// Endpoint untuk OpenMV kirim event kantuk
Route::post('/drowsy', [IoTApiController::class, 'receiveDrowsy']);