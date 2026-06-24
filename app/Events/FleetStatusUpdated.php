<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FleetStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $summary
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('fleet-tracking')];
    }

    public function broadcastAs(): string
    {
        return 'fleet.status.updated';
    }

    public function broadcastWith(): array
    {
        return $this->summary;
    }
}