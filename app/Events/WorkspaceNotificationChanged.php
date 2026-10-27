<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class WorkspaceNotificationChanged implements ShouldBroadcastNow
{
    public function __construct(public int $userId, public string $notificationId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId.'.notifications')];
    }

    public function broadcastAs(): string
    {
        return 'workspace.notification';
    }

    public function broadcastWith(): array
    {
        return ['notification_id' => $this->notificationId];
    }
}
