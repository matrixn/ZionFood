<?php

namespace App\Domain\Dining;

use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Tenancy\TenantAccess;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\RestaurantLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class StaffTableOrders
{
    public const ACTIVE = ['pending', 'accepted', 'preparing', 'ready', 'out_for_delivery'];

    public function canRead(User $actor, RestaurantLocation $location): bool
    {
        $access = app(TenantAccess::class);

        return $access->canFeature($actor, $location, 'tables') && ($access->canFeature($actor, $location, 'orders') || $access->canFeature($actor, $location, 'kitchen'));
    }

    public function canServe(User $actor, RestaurantLocation $location): bool
    {
        $access = app(TenantAccess::class);

        return $access->canFeature($actor, $location, 'tables') && ($access->canFeature($actor, $location, 'orders', true) || $access->canFeature($actor, $location, 'kitchen', true));
    }

    private function day(RestaurantLocation $location): array
    {
        $start = CarbonImmutable::now($location->timezone)->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }

    private function query(RestaurantLocation $location): Builder
    {
        return Order::query()->join('dining_sessions as sessions', 'orders.dining_session_id', '=', 'sessions.id')
            ->where('orders.organization_id', $location->organization_id)->where('orders.location_id', $location->id)
            ->where('sessions.organization_id', $location->organization_id)->where('sessions.location_id', $location->id)
            ->where('orders.fulfillment', 'dine_in');
    }

    public function summaries(User $actor, RestaurantLocation $location): array
    {
        if (! $this->canRead($actor, $location)) {
            return [];
        }
        [$start, $end] = $this->day($location);
        $active = $this->query($location)->whereIn('orders.status', self::ACTIVE)->selectRaw('sessions.dining_table_id as table_id, count(*) as active_count')->groupBy('sessions.dining_table_id')->get()->keyBy('table_id');
        $daily = $this->query($location)->where('orders.created_at', '>=', $start)->where('orders.created_at', '<', $end)->whereNotIn('orders.status', ['cancelled', 'rejected'])->selectRaw('sessions.dining_table_id as table_id, sum(orders.total) as daily_total')->groupBy('sessions.dining_table_id')->get()->keyBy('table_id');
        $ids = $active->keys()->merge($daily->keys())->unique();

        return $ids->mapWithKeys(fn ($id) => [$id => ['active_count' => (int) ($active->get($id)?->active_count ?? 0), 'daily_total' => (int) ($daily->get($id)?->daily_total ?? 0)]])->all();
    }

    public function detail(User $actor, DiningTable $table): array
    {
        $location = $table->location;
        abort_unless($this->canRead($actor, $location), 403);
        [$start, $end] = $this->day($location);
        $orders = $this->query($location)->where('sessions.dining_table_id', $table->id)
            ->where(fn ($q) => $q->whereIn('orders.status', self::ACTIVE)->orWhere(fn ($day) => $day->where('orders.status', 'completed')->where('orders.created_at', '>=', $start)->where('orders.created_at', '<', $end)))
            ->select('orders.*')->with('items.modifiers')->orderByDesc('orders.id')->cursorPaginate(30);

        return ['orders' => $orders->map(fn ($o) => [
            'public_id' => $o->public_id, 'status' => $o->status->value, 'version' => $o->version, 'total' => $o->total, 'currency' => $o->currency, 'created_at' => $o->created_at->toIso8601String(),
            'items' => $o->items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'variant_name' => $i->variant_name, 'quantity' => $i->quantity, 'total' => $i->total, 'modifiers' => $i->modifiers->map(fn ($m) => ['name' => $m->name, 'quantity' => $m->quantity, 'price' => $m->price])->values()])->values(),
        ])->values(), 'next_cursor' => $orders->nextCursor()?->encode(), 'can_serve' => $this->canServe($actor, $location)];
    }

    public function serve(User $actor, DiningTable $table, string $publicId, int $version): void
    {
        abort_unless($this->canServe($actor, $table->location), 403);
        $order = $this->query($table->location)->where('sessions.dining_table_id', $table->id)->where('orders.public_id', $publicId)->select('orders.*')->firstOrFail();
        $feature = app(TenantAccess::class)->canFeature($actor, $table->location, 'orders', true) ? 'orders' : 'kitchen';
        app(TransitionOrderStatus::class)->execute($actor, $order, OrderStatus::Completed, $version, null, $feature);
    }
}
