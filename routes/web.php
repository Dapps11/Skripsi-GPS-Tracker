<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveMapController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\Masters\DriverMasterController;
use App\Http\Controllers\Masters\VehicleMasterController;

Route::get('/', fn() => redirect()->route('login'));

Route::get('/login',  [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/live-map',           [LiveMapController::class, 'index'])->name('livemap.index');
    Route::get('/live-map/{vehicle}', [LiveMapController::class, 'show'])->name('livemap.show');

    Route::resource('devices', DeviceController::class);

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('drivers',  DriverMasterController::class);
        Route::resource('vehicles', VehicleMasterController::class);
    });

    // JSON polling endpoints
    Route::prefix('api/internal')->name('api.internal.')->group(function () {
        Route::get('/fleet-summary',     [\App\Http\Controllers\ApiController::class, 'fleetSummary'])->name('fleet');
        Route::get('/vehicles-position', [\App\Http\Controllers\ApiController::class, 'vehiclesPosition'])->name('positions');
        Route::get('/trip/{vehicle}',    [\App\Http\Controllers\ApiController::class, 'tripDetail'])->name('trip');
        Route::get('/alerts',            [\App\Http\Controllers\ApiController::class, 'alerts'])->name('alerts');
    });
});