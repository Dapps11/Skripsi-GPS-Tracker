<?php

use Illuminate\Support\Facades\Broadcast;

// Channel fleet-tracking — public, semua user terautentikasi bisa listen
Broadcast::channel('fleet-tracking', function ($user) {
    return true;
});

// Channel per trip
Broadcast::channel('trip.{vehicleId}', function ($user, $vehicleId) {
    return true;
});