<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class OrderChanged implements ShouldBroadcastNow
{
    public function __construct(public int $organizationId, public int $locationId, public array $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("organization.{$this->organizationId}.location.{$this->locationId}.orders")];
    }

    public function broadcastAs(): string
    {
        return 'order.changed';
    }

    public function broadcastWith(): array
    {
        return $this->message;
    }
}
