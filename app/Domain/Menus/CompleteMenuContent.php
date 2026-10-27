<?php

namespace App\Domain\Menus;

use App\Events\WorkspaceNotificationChanged;
use App\Models\MenuContentDraft;
use App\Models\WorkspaceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompleteMenuContent
{
    public function finish(int $id, string $status, array $fields = []): void
    {
        DB::transaction(function () use ($id, $status, $fields) {
            $draft = MenuContentDraft::whereKey($id)->lockForUpdate()->first();
            if (! $draft || ! in_array($draft->status, ['queued', 'processing'], true)) {
                return;
            }
            $draft->update([...$fields, 'status' => $status]);
            $notification = WorkspaceNotification::firstOrCreate(['user_id' => $draft->requested_by, 'draft_id' => $draft->id]);
            DB::afterCommit(function () use ($notification) {
                try {
                    event(new WorkspaceNotificationChanged($notification->user_id, $notification->public_id));
                } catch (\Throwable $error) {
                    // The durable inbox/background fetch recovers missed realtime delivery.
                    Log::warning('workspace_notification.broadcast_failed', ['notification_id' => $notification->public_id, 'exception_class' => get_class($error)]);
                }
            });
        }, 3);
    }
}
