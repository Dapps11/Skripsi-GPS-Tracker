<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'driver_code', 'full_name', 'phone', 'license_number',
        'license_expiry', 'address', 'photo', 'status', 'notes',
    ];

    public function iotDevices()
    {
        return $this->hasMany(IotDevice::class, 'driver_id');
    }
}