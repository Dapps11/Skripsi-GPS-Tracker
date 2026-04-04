<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vehicle_code', 'name', 'license_plate', 'vehicle_type',
        'brand', 'model', 'year', 'color', 'capacity_liters',
        'status', 'notes',
    ];

    public function iotDevices()
    {
        return $this->hasMany(IotDevice::class, 'vehicle_id');
    }

    public function activeTrip()
    {
        return $this->hasOne(Trip::class, 'vehicle_id')
                    ->where('status', 'in_progress');
    }
}