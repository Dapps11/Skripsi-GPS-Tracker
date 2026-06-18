<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VehiclePositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $vehicleData
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('fleet-tracking'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vehicle.position.updated';
    }

    public function broadcastWith(): array
    {
        return $this->vehicleData;
    }
}