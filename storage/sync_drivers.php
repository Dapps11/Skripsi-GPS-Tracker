<?php
use App\Models\Driver;
use App\Models\Trip;

// Find all drivers currently marked as on_duty
$drivers = Driver::where('status', 'on_duty')->get();

$updatedCount = 0;

foreach ($drivers as $driver) {
    // Check if this driver is currently assigned to an 'in_progress' trip
    $hasActiveTrip = Trip::where('driver_id', $driver->id)
                         ->where('status', 'in_progress')
                         ->exists();
                         
    if (!$hasActiveTrip) {
        $driver->update(['status' => 'available']);
        $updatedCount++;
    }
}

echo "Berhasil mengubah status $updatedCount supir dari on_duty ke available.\n";
