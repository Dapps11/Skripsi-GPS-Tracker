<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$trip = \App\Models\Trip::where('trip_code', 'TRIP-D9BCA5')->first();
$gpsPoints = \App\Models\GpsTelemetry::where('trip_id', $trip->id)
    ->orderBy('gps_timestamp')
    ->get();

$gapThresholdSec = 30;
$signalGaps      = [];
$rawCount        = count($gpsPoints);

for ($i = 0; $i < $rawCount - 1; $i++) {
    $rawTs1 = $gpsPoints[$i]->gps_timestamp;
    $rawTs2 = $gpsPoints[$i + 1]->gps_timestamp;
    $t1     = \Carbon\Carbon::parse(($rawTs1 instanceof \Carbon\Carbon ? $rawTs1->format('Y-m-d H:i:s') : $rawTs1), 'UTC');
    $t2     = \Carbon\Carbon::parse(($rawTs2 instanceof \Carbon\Carbon ? $rawTs2->format('Y-m-d H:i:s') : $rawTs2), 'UTC');
    $gapSec = abs($t1->diffInSeconds($t2));

    if ($gapSec > $gapThresholdSec) {
        // Cek apakah ada data kantuk (driver_monitoring_events) di antara t1 dan t2
        $drowsyEventsCount = \App\Models\DriverMonitoringEvent::where('trip_id', $trip->id)
            ->where('event_timestamp', '>', $t1->format('Y-m-d H:i:s'))
            ->where('event_timestamp', '<', $t2->format('Y-m-d H:i:s'))
            ->count();
            
        $drowsyEvents = \App\Models\DriverMonitoringEvent::where('trip_id', $trip->id)
            ->where('event_timestamp', '>', $t1->format('Y-m-d H:i:s'))
            ->where('event_timestamp', '<', $t2->format('Y-m-d H:i:s'))
            ->get(['event_timestamp', 'event_type', 'is_alarm']);

        $signalGaps[] = [
            'start_at'           => $t1->setTimezone('Asia/Jakarta')->format('H:i:s'),
            'end_at'             => $t2->setTimezone('Asia/Jakarta')->format('H:i:s'),
            'duration_sec'       => $gapSec,
            'drowsy_events_cnt'  => $drowsyEventsCount,
            'events_sample'      => $drowsyEvents->take(3)->toArray()
        ];
    }
}

echo json_encode($signalGaps, JSON_PRETTY_PRINT);
