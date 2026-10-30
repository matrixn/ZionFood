<?php

namespace App\Domain\Orders;

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\RestaurantLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReleaseScheduledOrders
{
    public function execute(): int
    {
        $released = 0;
        Order::whereNotNull('scheduled_for')->whereNull('schedule_released_at')->where('preparation_at', '<=', now())->whereNotIn('status', ['cancelled', 'rejected', 'completed'])->chunkById(100, function ($orders) use (&$released) {
            foreach ($orders as $context) {
                DB::transaction(function () use ($context, &$released) {
                    RestaurantLocation::whereKey($context->location_id)->lockForUpdate()->firstOrFail();
                    $order = Order::whereKey($context->id)->lockForUpdate()->firstOrFail();
                    if ($order->schedule_released_at || in_array($order->status->value, ['cancelled', 'rejected', 'completed'])) {
                        return;
                    }
                    $order->update(['schedule_released_at' => now(), 'version' => $order->version + 1]);
                    OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $order->organization_id, 'location_id' => $order->location_id, 'type' => 'order.updated', 'payload' => ['order_id' => $order->public_id, 'version' => $order->version, 'reason' => 'schedule_released']]);
                    $released++;
                });
            }
        });

        return $released;
    }
}
