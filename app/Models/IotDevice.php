<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IotDevice extends Model
{
    use SoftDeletes;

    protected $table = 'iot_devices';

    protected $fillable = [
        'device_id', 'device_type', 'imei', 'iccid', 'apn',
        'phone_number', 'network_operator', 'vehicle_id', 'driver_id',
        'status', 'last_heartbeat', 'last_latitude',
        'last_longitude', 'last_speed_kmh', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat' => 'datetime',
            'last_latitude'  => 'float',
            'last_longitude' => 'float',
            'last_speed_kmh' => 'float',
        ];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}