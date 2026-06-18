<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'trip_code',
        'vehicle_id',
        'driver_id',
        'device_id',
        'origin_name',
        'origin_address',
        'origin_lat',
        'origin_lng',
        'dest_name',
        'dest_address',
        'dest_lat',
        'dest_lng',
        'departed_at',
        'estimated_arrival_at',
        'arrived_at',
        'total_distance_km',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'origin_lat'           => 'float',
            'origin_lng'           => 'float',
            'dest_lat'             => 'float',
            'dest_lng'             => 'float',
            'departed_at'          => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'arrived_at'           => 'datetime',
        ];
    }

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function driver()  { return $this->belongsTo(Driver::class); }
    public function device()  { return $this->belongsTo(IotDevice::class, 'device_id'); }

    public function gpsPoints()
    {
        return $this->hasMany(GpsTelemetry::class, 'trip_id')
                    ->orderBy('gps_timestamp');
    }
}