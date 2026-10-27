<?php

namespace App\Domain\Dining;

use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\Order;

class ActiveTableOrders
{
    public function execute(DiningTable $table, ?string $token): array
    {
        abort_unless($token && strlen($token) === 64, 404);
        // This bearer grants receipt access, independently of permission to place new orders.
        $session = DiningSession::where('organization_id', $table->organization_id)->where('location_id', $table->location_id)->where('dining_table_id', $table->id)->where('token_hash', hash('sha256', $token))->firstOrFail();
        $orders = Order::where('organization_id', $table->organization_id)->where('location_id', $table->location_id)->where('dining_session_id', $session->id)->where('fulfillment', 'dine_in')->whereIn('status', ['pending', 'accepted', 'preparing', 'ready', 'out_for_delivery'])->with('items.modifiers')->orderBy('id')->cursorPaginate(30);

        return ['orders' => $orders->map(fn ($o) => ['public_id' => $o->public_id, 'status' => $o->status->value, 'version' => $o->version, 'currency' => $o->currency, 'total' => $o->total, 'created_at' => $o->created_at->toIso8601String(), 'items' => $o->items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'variant_name' => $i->variant_name, 'quantity' => $i->quantity, 'total' => $i->total, 'modifiers' => $i->modifiers->map(fn ($m) => ['name' => $m->name, 'quantity' => $m->quantity, 'price' => $m->price])->values()])->values()])->values(), 'next_cursor' => $orders->nextCursor()?->encode()];
    }
}
