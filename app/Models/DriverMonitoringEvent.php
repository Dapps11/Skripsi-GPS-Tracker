<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverMonitoringEvent extends Model
{
    public $timestamps = false;

    protected $table = 'driver_monitoring_events';

    protected $fillable = [
        'device_id', 'vehicle_id', 'driver_id', 'trip_id',
        'event_type', 'confidence', 'driver_status',
        'snapshot_path', 'event_timestamp', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'event_timestamp' => 'datetime',
            'recorded_at'     => 'datetime',
        ];
    }
}