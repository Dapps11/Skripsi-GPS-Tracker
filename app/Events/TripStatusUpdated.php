<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $vehicleId,
        public readonly array $tripData
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('fleet-tracking'),
            new Channel("trip.{$this->vehicleId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trip.status.updated';
    }

    public function broadcastWith(): array
    {
        return array_merge(['vehicle_id' => $this->vehicleId], $this->tripData);
    }
}