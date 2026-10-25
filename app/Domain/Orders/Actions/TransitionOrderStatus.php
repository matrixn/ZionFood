<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\OrderStatus;
use App\Domain\Tenancy\TenantAccess;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransitionOrderStatus
{
    public function execute(User $actor, Order $context, OrderStatus $next, int $version, ?string $reason = null): Order
    {
        app(TenantAccess::class)->requireOperate($actor, $context->location);

        return DB::transaction(function () use ($actor, $context, $next, $version, $reason) {
            $order = Order::whereKey($context->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->version === $version, 409, __('Order changed. Refresh and try again.'));
            if (! in_array($next, $order->status->transitions($order->fulfillment), true)) {
                throw ValidationException::withMessages(['status' => __('This status transition is not allowed.')]);
            }
            if ($next === OrderStatus::Rejected && blank($reason)) {
                throw ValidationException::withMessages(['reason' => __('A rejection reason is required.')]);
            }
            $old = $order->status;
            $order->update(['status' => $next, 'version' => $version + 1]);
            OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => $old->value, 'to_status' => $next->value, 'actor_id' => $actor->id, 'reason' => $reason]);
            OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $order->organization_id, 'location_id' => $order->location_id, 'type' => 'order.updated', 'payload' => ['order_id' => $order->public_id, 'version' => $order->version]]);

            return $order;
        }, 3);
    }
}
