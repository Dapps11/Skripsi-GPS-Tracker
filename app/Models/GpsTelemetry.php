<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsTelemetry extends Model
{
    public $timestamps = false;

    protected $table = 'gps_telemetry';

    protected $fillable = [
        'device_id', 'vehicle_id', 'trip_id', 'latitude', 'longitude', 
        'speed_kmh', 'heading', 'gsm_signal', 'network_type', 
        'gps_timestamp', 'recorded_at', 'accuracy_m',
        'satellites', 'hdop', 'pdop', 'vdop', 'fix_mode'
    ];

    protected function casts(): array
    {
        return [
            'latitude'      => 'float',
            'longitude'     => 'float',
            'gps_timestamp' => 'datetime',
            'recorded_at'   => 'datetime',
        ];
    }

    public function getGpsTimestampWibAttribute()
    {
        return \Carbon\Carbon::parse(
            $this->gps_timestamp->format('Y-m-d H:i:s'),
            'UTC'
        )->setTimezone('Asia/Jakarta');
    }
}