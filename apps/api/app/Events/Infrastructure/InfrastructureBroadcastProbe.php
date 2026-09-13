<?php

namespace App\Events\Infrastructure;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InfrastructureBroadcastProbe implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('infrastructure.diagnostic')];
    }

    public function broadcastAs(): string
    {
        return 'infrastructure.probe';
    }

    public function broadcastQueue(): string
    {
        return 'diagnostic';
    }

    /**
     * @return array{status: string}
     */
    public function broadcastWith(): array
    {
        return ['status' => 'diagnostic'];
    }
}
