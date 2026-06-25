<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveMapController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\TripController;
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
        Route::get('vehicles/{vehicle}/history', [VehicleMasterController::class, 'history'])
             ->name('vehicles.history');
    });

    // JSON polling endpoints
    Route::prefix('api/internal')->name('api.internal.')->group(function () {
        Route::get('/fleet-summary',          [\App\Http\Controllers\ApiController::class, 'fleetSummary'])->name('fleet');
        Route::get('/vehicles-position',      [\App\Http\Controllers\ApiController::class, 'vehiclesPosition'])->name('positions');
        Route::get('/trip/{vehicle}',         [\App\Http\Controllers\ApiController::class, 'tripDetail'])->name('trip');
        Route::get('/alerts',                 [\App\Http\Controllers\ApiController::class, 'alerts'])->name('alerts');
        Route::get('/alerts/unread-count',    [\App\Http\Controllers\ApiController::class, 'alertsUnreadCount'])->name('alerts.unread');
        Route::post('/alerts/read-all',       [\App\Http\Controllers\ApiController::class, 'markAlertsRead'])->name('alerts.read');
        Route::post('/alerts/{id}/read',      [\App\Http\Controllers\ApiController::class, 'markAlertRead'])->name('alerts.read.one');
        Route::get('/search',                 [\App\Http\Controllers\ApiController::class, 'search'])->name('search');
        Route::get('/vehicle-device/{vehicle}', function (\App\Models\Vehicle $vehicle) {
            $device = \App\Models\IotDevice::where('vehicle_id', $vehicle->id)
                                        ->whereNull('deleted_at')
                                        ->with('driver')
                                        ->first();
            return response()->json([
                'device' => $device,
                'driver' => $device?->driver,
            ]);
        })->name('vehicle.device');
    });

    // GPS Tester — hanya untuk development
    Route::get('/gps-tester', function () {
        $devices = \App\Models\IotDevice::whereNull('deleted_at')
                                        ->with('vehicle')
                                        ->orderByDesc('created_at')
                                        ->get();
        return view('gps-tester', compact('devices'));
    })->name('gps.tester');

    // Map preference — simpan pilihan user
    Route::post('/map-preference', function (\Illuminate\Http\Request $r) {
        $type = in_array($r->input('type'), ['osm', 'gmaps']) ? $r->input('type') : 'osm';
        session(['map_type' => $type]);
        return response()->json(['ok' => true, 'type' => $type]);
    })->middleware('auth')->name('map.preference');

    Route::resource('trips', TripController::class);
    Route::post('trips/{trip}/start',    [TripController::class, 'start'])->name('trips.start');
    Route::post('trips/{trip}/complete', [TripController::class, 'complete'])->name('trips.complete');

});