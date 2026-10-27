<?php

namespace App\Domain\Analytics;

use App\Domain\Tenancy\TenantAccess;
use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RestaurantOverview
{
    public function build(User $actor, RestaurantLocation $location, int $days): array
    {
        abort_unless(in_array($days, [7, 30, 90], true), 422);
        $access = app(TenantAccess::class);
        $access->requireOperate($actor, $location);
        $now = CarbonImmutable::now($location->timezone);
        $first = $now->startOfDay()->subDays($days - 1);
        $until = $now->addDay()->startOfDay()->utc();
        $start = $first->utc();
        $scope = fn ($query) => $query->where('organization_id', $location->organization_id)->where('location_id', $location->id);
        $can = fn ($feature) => $access->canFeature($actor, $location, $feature);
        $data = ['days' => $days, 'from' => $first->toDateString(), 'to' => $now->toDateString(), 'timezone' => $location->timezone, 'orders' => null, 'reservations' => null, 'menu' => null, 'tables' => null, 'visits' => null];
        if ($can('orders')) {
            $query = $scope(Order::query())->where('created_at', '>=', $start)->where('created_at', '<', $until);
            $status = (clone $query)->selectRaw('status, COUNT(*) as count, SUM(total) as total')->groupBy('status')->get();
            $completed = $status->first(fn ($row) => $row->status->value === 'completed');
            $count = (int) $status->sum('count');
            $value = (int) ($completed?->total ?? 0);
            $completedCount = (int) ($completed?->count ?? 0);
            // UTC boundaries are computed per local calendar day, including DST; the grouped query is portable across SQLite/MariaDB.
            $calendar = null;
            for ($day = $first; $day->lte($now); $day = $day->addDay()) {
                $part = DB::query()->selectRaw('? as day, ? as starts, ? as ends', [$day->toDateString(), $day->utc()->format('Y-m-d H:i:s'), $day->addDay()->utc()->format('Y-m-d H:i:s')]);
                $calendar = $calendar ? $calendar->unionAll($part) : $part;
            }
            $series = DB::query()->fromSub($calendar, 'calendar')->leftJoin('orders', function ($join) use ($location) {
                $join->on('orders.created_at', '>=', 'calendar.starts')->on('orders.created_at', '<', 'calendar.ends')->where('orders.organization_id', $location->organization_id)->where('orders.location_id', $location->id);
            })->selectRaw("calendar.day, COUNT(orders.id) as orders, COALESCE(SUM(CASE WHEN orders.status = 'completed' THEN orders.total ELSE 0 END),0) as value")->groupBy('calendar.day')->orderBy('calendar.day')->get();
            $top = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.organization_id', $location->organization_id)->where('orders.location_id', $location->id)->where('orders.status', 'completed')->where('orders.created_at', '>=', $start)->where('orders.created_at', '<', $until)->selectRaw('order_items.product_public_id, MAX(order_items.name) as name, SUM(order_items.quantity) as quantity, SUM(order_items.total) as value')->groupBy('order_items.product_public_id')->orderByDesc('quantity')->orderBy('order_items.product_public_id')->limit(5)->get();
            $data['orders'] = ['count' => $count, 'completed' => $completedCount, 'value' => $value, 'average' => $completedCount ? (int) round($value / $completedCount) : 0, 'cancelled' => (int) $status->filter(fn ($row) => in_array($row->status->value, ['cancelled', 'rejected']))->sum('count'), 'active' => $scope(Order::query())->whereNotIn('status', ['completed', 'cancelled', 'rejected'])->count(), 'today' => $scope(Order::query())->where('created_at', '>=', $now->startOfDay()->utc())->count(), 'series' => $series, 'top_products' => $top, 'status' => $status->map(fn ($row) => ['status' => $row->status->value, 'count' => (int) $row->count]), 'fulfillment' => (clone $query)->selectRaw('fulfillment, COUNT(*) as count')->groupBy('fulfillment')->orderByDesc('count')->get()];
        }
        if ($can('reservations')) {
            $query = $scope(Reservation::query())->where('starts_at', '>=', $start)->where('starts_at', '<', $until);
            $valid = (clone $query)->whereNotIn('status', ['cancelled', 'rejected', 'expired', 'no_show'])->where(fn ($q) => $q->where('status', '!=', 'pending')->orWhere('hold_expires_at', '>', $now->utc()));
            $data['reservations'] = ['count' => (clone $query)->count(), 'guests' => (int) (clone $valid)->sum('party_size'), 'today' => (clone $valid)->where('starts_at', '>=', $now->startOfDay()->utc())->count(), 'upcoming' => $scope(Reservation::query())->where('starts_at', '>=', $now->utc())->whereIn('status', ['confirmed', 'pending'])->where(fn ($q) => $q->where('status', 'confirmed')->orWhere('hold_expires_at', '>', $now->utc()))->count(), 'status' => (clone $query)->selectRaw('status, COUNT(*) as count')->groupBy('status')->orderByDesc('count')->get()];
        }
        if ($can('menu')) {
            $menus = $location->menus()->pluck('menus.id');
            $items = MenuItem::where('organization_id', $location->organization_id)->whereHas('category', fn ($q) => $q->whereIn('menu_id', $menus));
            $data['menu'] = ['recipes' => (clone $items)->count(), 'available' => (clone $items)->where('is_available', true)->count(), 'categories' => DB::table('menu_categories')->where('organization_id', $location->organization_id)->whereIn('menu_id', $menus)->count(), 'menus' => $menus->count()];
            $data['visits'] = ['count' => (int) DB::table('menu_visit_stats')->where('organization_id', $location->organization_id)->where('location_id', $location->id)->whereBetween('day', [$first->toDateString(), $now->toDateString()])->sum('visits'), 'tracking_since' => DB::table('menu_visit_stats')->where('location_id', $location->id)->min('day'), 'series' => DB::table('menu_visit_stats')->where('location_id', $location->id)->whereBetween('day', [$first->toDateString(), $now->toDateString()])->orderBy('day')->get(['day', 'visits'])];
        }
        if ($can('tables')) {
            $tables = $scope(DiningTable::query())->where('is_active', true);
            $occupied = $scope(DiningSession::query())->whereHas('table', fn ($q) => $q->where('is_active', true))->whereNull('closed_at')->where('expires_at', '>', $now->utc())->distinct()->count('dining_table_id');
            $data['tables'] = ['count' => (clone $tables)->count(), 'seats' => (int) (clone $tables)->sum('seats'), 'manually_open' => (clone $tables)->where('ordering_paused', false)->count(), 'occupied' => $occupied, 'top' => []];
            if ($can('orders')) {
                $data['tables']['top'] = DB::table('orders')->where('organization_id', $location->organization_id)->where('location_id', $location->id)->where('status', 'completed')->whereNotNull('table_code')->where('created_at', '>=', $start)->where('created_at', '<', $until)->selectRaw('table_code, table_area, COUNT(*) as orders, SUM(total) as value')->groupBy('table_code', 'table_area')->orderByDesc('orders')->orderBy('table_code')->limit(5)->get();
            }
            if ($can('reservations')) {
                $data['tables']['reserved'] = DB::table('reservations')->join('dining_tables', 'dining_tables.id', '=', 'reservations.dining_table_id')->where('reservations.organization_id', $location->organization_id)->where('reservations.location_id', $location->id)->where('reservations.starts_at', '>=', $start)->where('reservations.starts_at', '<', $until)->whereIn('reservations.status', ['confirmed', 'seated', 'completed'])->selectRaw('dining_tables.code, dining_tables.area, COUNT(*) as reservations, SUM(reservations.party_size) as guests')->groupBy('dining_tables.id', 'dining_tables.code', 'dining_tables.area')->orderByDesc('reservations')->limit(5)->get();
            }
        }

        return $data;
    }
}
