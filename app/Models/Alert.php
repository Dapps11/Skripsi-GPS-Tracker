<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'alert_type', 'severity', 'vehicle_id', 'driver_id',
        'device_id', 'trip_id', 'user_id', 'title', 'message',
        'meta_data', 'is_read', 'read_at', 'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_data'    => 'array',
            'is_read'      => 'boolean',
            'triggered_at' => 'datetime',
            'read_at'      => 'datetime',
        ];
    }

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function driver()  { return $this->belongsTo(Driver::class); }
}