<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$trip = \App\Models\Trip::where('trip_code', 'TRIP-D9BCA5')->first();
$gpsPoints = \App\Models\GpsTelemetry::where('trip_id', $trip->id)
    ->orderBy('gps_timestamp')
    ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);

$ctrl = app(\App\Http\Controllers\TripController::class);
$reflection = new ReflectionClass(get_class($ctrl));

$methodSmooth = $reflection->getMethod('smoothTrack');
$methodSmooth->setAccessible(true);
$gpsPointsForMap = $methodSmooth->invokeArgs($ctrl, [$gpsPoints]);

$methodStops = $reflection->getMethod('detectStops');
$methodStops->setAccessible(true);
$stops = $methodStops->invokeArgs($ctrl, [$gpsPoints]);

echo json_encode(['stops' => $stops], JSON_PRETTY_PRINT);
