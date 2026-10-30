<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\RestaurantOverview;
use App\Domain\Dining\ActiveTableOrders;
use App\Domain\Dining\StaffTableOrders;
use App\Domain\Dining\TableSessions;
use App\Domain\Integrations\ManageWebhook;
use App\Domain\Integrations\WordpressAccess;
use App\Domain\Menus\Actions\UpdateMenuItem;
use App\Domain\Menus\SaveMenuCategory;
use App\Domain\Menus\SaveModifierGroup;
use App\Domain\Orders\Actions\GetOrderingAvailability;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\PriceOrder;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\ManagePromotion;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\ResolveFulfillmentTime;
use App\Domain\Reservations\CreateReservation;
use App\Domain\Reservations\TransitionReservation;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\MenuItemRequest;
use App\Http\Resources\OrderResource;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Promotion;
use App\Models\Reservation;
use App\Models\RestaurantLocation;
use App\Models\WebhookEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WordpressApiController extends Controller
{
    private function context(Request $r, string $id, string $ability, ?string $feature = null, bool $write = false): RestaurantLocation
    {
        $l = app(WordpressAccess::class)->location($r, $id, $ability);
        if ($feature) {
            app(TenantAccess::class)->requireFeature($r->user(), $l, $feature, $write);
        }

        return $l;
    }

    private function key(Request $r): string
    {
        return 'wp_'.hash('sha256', app(WordpressAccess::class)->connection($r)->id.'|'.$r->header('Idempotency-Key'));
    }

    public function bootstrap(Request $r)
    {
        $c = app(WordpressAccess::class)->connection($r);

        return response()->json(['api_version' => '1', 'application_version' => config('zionfood.version'), 'connection_id' => $c->public_id,
            'abilities' => $r->user()->currentAccessToken()->abilities,
            'locations' => RestaurantLocation::where('restaurant_id', $c->restaurant_id)->where('organization_id', $c->organization_id)->whereIn('id', $c->location_ids)->get()->map(fn ($l) => $l->only('public_id', 'name', 'currency', 'timezone', 'storefront_path', 'commerce_settings')),
            'capabilities' => ['menu' => true, 'photos' => true, 'variants' => true, 'modifier_quantities' => true, 'food_information' => true, 'translations' => true, 'pickup' => true, 'delivery_zones' => true, 'scheduled_orders' => true, 'reservations' => true, 'reservation_preorders' => true, 'qr_tables' => true, 'promotions' => ['percent', 'fixed', 'free_delivery', 'buy_x_get_y'], 'webhooks' => true, 'event_recovery' => true, 'payments' => false, 'marketing_automation' => false, 'mobile_apps' => false, 'printing' => false],
            'links' => ['documentation' => 'https://github.com/matrixn/ZionFood/blob/main/docs/WORDPRESS_API.md'], 'server_time' => now()->toIso8601String()]);
    }

    public function menu(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'storefront:read');
        $data = app(PublicOrderingController::class)->data($l);
        $data['location']['commerce_settings'] = $l->commerce_settings;
        $data['promotions'] = Promotion::where('restaurant_id', $l->restaurant_id)->where('organization_id', $l->organization_id)->where(fn ($q) => $q->whereNull('location_id')->orWhere('location_id', $l->id))->where('active', true)->whereNull('code')->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->get(['public_id', 'name', 'kind', 'value', 'minimum_order', 'max_discount', 'fulfillments', 'buy_quantity', 'reward_quantity', 'product_ids', 'ends_at']);

        return response()->json($data);
    }

    public function availability(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'storefront:read');

        return app(PublicOrderingController::class)->availability($r, $l->public_id);
    }

    public function slots(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'storefront:read');
        $d = $r->validate(['date' => 'required|date_format:Y-m-d', 'fulfillment' => 'required|in:pickup,delivery']);

        return response()->json(['slots' => app(ResolveFulfillmentTime::class)->slots($l, $d['date'], $d['fulfillment']), 'timezone' => $l->timezone]);
    }

    public function quote(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'checkout:write');

        return DB::transaction(function () use ($r, $l) {
            $l = RestaurantLocation::whereKey($l->id)->lockForUpdate()->firstOrFail();
            $input = validator($r->all(), CheckoutRequest::constraints($r->all()))->validate();
            $timing = app(ResolveFulfillmentTime::class)->execute($l, $input);
            if ($timing['scheduled_for']) {
                $input['scheduled_for'] = $timing['scheduled_for']->toIso8601String();
            }
            if ($input['fulfillment'] === 'dine_in' && ! $timing['reservation']) {
                app(PlaceOrderAction::class)->diningSession($l, $input);
            }
            app(PlaceOrderAction::class)->checkAvailability($l, $input['fulfillment'], (bool) $timing['scheduled_for']);
            $pricing = app(PriceOrder::class)->execute($l, $input);
            unset($pricing['lines']);

            return response()->json([...$pricing, 'currency' => $l->currency, 'scheduled_for' => $timing['scheduled_for'], 'binding' => false]);
        });
    }

    public function checkout(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'checkout:write');
        $result = app(PlaceOrderAction::class)->execute($l, $r->all(), $this->key($r));
        if (! $result['replayed']) {
            $result['order']->update(['wordpress_connection_id' => app(WordpressAccess::class)->connection($r)->id]);
        }

        return response()->json(['order_id' => $result['order']->public_id, 'tracking_token' => $result['tracking_token'], 'total' => $result['order']->total, 'discount' => $result['order']->discount, 'scheduled_for' => $result['order']->scheduled_for, 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function trackOrder(Request $r, string $location, string $order)
    {
        $l = $this->context($r, $location, 'checkout:write');
        $d = $r->validate(['tracking_token' => 'required|string|size:64']);
        $o = Order::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('public_id', $order)->where('tracking_token_hash', hash('sha256', $d['tracking_token']))->firstOrFail();

        return response()->json([...$o->only('public_id', 'status', 'version', 'total', 'discount', 'currency', 'scheduled_for'), 'items' => $o->items()->with('modifiers')->get(), 'history' => $o->history()->get(['to_status', 'created_at'])]);
    }

    public function orders(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'orders:read', 'orders');

        return app(OperationsController::class)->index($r, $l->public_id);
    }

    public function order(Request $r, string $location, string $order)
    {
        $l = $this->context($r, $location, 'orders:read', 'orders');

        return new OrderResource(Order::where('organization_id', $l->organization_id)->where('location_id', $l->id)->where('public_id', $order)->with('items.modifiers', 'history')->firstOrFail());
    }

    public function reservation(Request $r, string $location, string $reservation)
    {
        $l = $this->context($r, $location, 'reservations:read', 'reservations');

        return response()->json(Reservation::where('organization_id', $l->organization_id)->where('location_id', $l->id)->where('public_id', $reservation)->with('table', 'history')->firstOrFail());
    }

    public function promotion(Request $r, string $location, string $promotion)
    {
        $l = $this->context($r, $location, 'promotions:manage', 'orders', true);

        return response()->json(Promotion::where('organization_id', $l->organization_id)->where('restaurant_id', $l->restaurant_id)->where('location_id', $l->id)->where('public_id', $promotion)->firstOrFail());
    }

    public function kitchen(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'orders:read', 'kitchen');

        return app(OperationsController::class)->kitchen($r, $l->public_id);
    }

    public function orderStatus(Request $r, string $location, string $order)
    {
        $l = $this->context($r, $location, 'orders:manage', 'orders', true);
        $o = Order::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('public_id', $order)->firstOrFail();
        $d = $r->validate(['status' => ['required', Rule::enum(OrderStatus::class)], 'version' => 'required|integer|min:1', 'reason' => 'nullable|string|max:500']);

        return new OrderResource(app(TransitionOrderStatus::class)->execute($r->user(), $o, OrderStatus::from($d['status']), $d['version'], $d['reason'] ?? null)->load('items.modifiers', 'history'));
    }

    public function reservationSlots(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'storefront:read');

        return app(ReservationController::class)->available($r, $l->public_id, app(PublicOrderingController::class));
    }

    public function reserve(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'reservations:create');
        $v = app(CreateReservation::class)->execute($l, $r->all(), $this->key($r));

        return response()->json(['reservation_id' => $v['reservation']->public_id, 'token' => $v['token'], 'replayed' => $v['replayed']], $v['replayed'] ? 200 : 201);
    }

    public function reservations(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'reservations:read', 'reservations');
        $d = $r->validate(['date' => 'required|date_format:Y-m-d']);
        $start = CarbonImmutable::parse($d['date'], $l->timezone)->startOfDay();

        return Reservation::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('starts_at', '>=', $start->utc())->where('starts_at', '<', $start->addDay()->utc())->with('table')->orderBy('starts_at')->paginate(50);
    }

    public function reservationStatus(Request $r, string $location, string $reservation)
    {
        $l = $this->context($r, $location, 'reservations:manage', 'reservations', true);
        $d = $r->validate(['status' => 'required|in:confirmed,rejected,cancelled,seated,completed,no_show', 'version' => 'required|integer|min:1']);
        $b = Reservation::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('public_id', $reservation)->firstOrFail();

        return response()->json(app(TransitionReservation::class)->execute($b, $d['status'], $d['version'], $r->user()));
    }

    public function reservationTrack(Request $r, string $location, string $reservation, bool $cancel = false)
    {
        $l = $this->context($r, $location, 'reservations:create');
        $d = $r->validate(['reservation_token' => 'required|string|size:64', 'version' => 'sometimes|integer|min:1']);
        $b = Reservation::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('public_id', $reservation)->where('token_hash', hash('sha256', $d['reservation_token']))->firstOrFail();
        if ($cancel) {
            $r->validate(['version' => 'required|integer|min:1']);
            $b = app(TransitionReservation::class)->execute($b, 'cancelled', $d['version']);
        }

        return response()->json($b->only('public_id', 'status', 'version', 'starts_at', 'ends_at', 'party_size', 'timezone', 'cancellation_deadline', 'hold_expires_at'));
    }

    public function reservationCancel(Request $r, string $location, string $reservation)
    {
        return $this->reservationTrack($r, $location, $reservation, true);
    }

    public function tables(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'tables:read', 'tables');
        $manage = $r->user()->tokenCan('tables:manage') && app(TenantAccess::class)->canFeature($r->user(), $l, 'tables', true);

        return response()->json(['tables' => DiningTable::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('is_active', true)->get()->map(fn ($t) => [...$t->only('public_id', 'code', 'area', 'seats', 'ordering_paused'), 'qr_url' => $manage ? rtrim(config('app.url'), '/').'/table/'.$t->public_id.'#token='.Crypt::decryptString($t->qr_token_encrypted) : null]), 'availability' => app(GetOrderingAvailability::class)->execute($l)]);
    }

    private function table(Request $r, string $location, string $table, string $ability, bool $write = false): DiningTable
    {
        $l = $this->context($r, $location, $ability, 'tables', $write);

        return DiningTable::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('public_id', $table)->firstOrFail();
    }

    public function tableOperation(Request $r, string $location, string $table, string $operation)
    {
        $t = $this->table($r, $location, $table, 'tables:manage', true);
        $s = app(TableSessions::class);
        match ($operation) {
            'open' => $s->open($t, $r->user()), 'close' => $s->close($r->user(), $t), 'rotate' => $s->rotate($r->user(), $t), default => abort(404)
        };

        return response()->noContent();
    }

    public function tableOrders(Request $r, string $location, string $table)
    {
        $t = $this->table($r, $location, $table, 'tables:read');
        app(WordpressAccess::class)->ability($r, 'orders:read');

        return response()->json(app(StaffTableOrders::class)->detail($r->user(), $t));
    }

    public function tableServe(Request $r, string $location, string $table, string $order)
    {
        $t = $this->table($r, $location, $table, 'tables:manage', true);
        app(WordpressAccess::class)->ability($r, 'orders:manage');
        $d = $r->validate(['version' => 'required|integer|min:1']);
        app(StaffTableOrders::class)->serve($r->user(), $t, $order, $d['version']);

        return response()->noContent();
    }

    public function tableConnect(Request $r, string $location, string $table)
    {
        $l = $this->context($r, $location, 'checkout:write');
        DiningTable::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('public_id', $table)->firstOrFail();

        return app(DiningController::class)->connect($r, $table, app(TableSessions::class));
    }

    public function guestTableOrders(Request $r, string $location, string $table)
    {
        $l = $this->context($r, $location, 'checkout:write');
        $d = $r->validate(['table_session_token' => 'required|string|size:64', 'cursor' => 'nullable|string|max:512']);
        $t = DiningTable::where('location_id', $l->id)->where('organization_id', $l->organization_id)->where('public_id', $table)->firstOrFail();

        return response()->json(app(ActiveTableOrders::class)->execute($t, $d['table_session_token']));
    }

    public function category(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'menu:write', 'menu', true);

        return response()->json(app(SaveMenuCategory::class)->execute($r->user(), $l, $r->all()));
    }

    public function modifier(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'menu:write', 'menu', true);

        return response()->json(app(SaveModifierGroup::class)->execute($r->user(), $l, $r->all()));
    }

    public function editableMenu(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'menu:read', 'menu');

        return app(MenuController::class)->show($r, $l->public_id);
    }

    public function createItem(MenuItemRequest $r, string $location)
    {
        $l = $this->context($r, $location, 'menu:write', 'menu', true);

        return app(MenuController::class)->store($r, $l->public_id, app(UpdateMenuItem::class));
    }

    public function updateItem(MenuItemRequest $r, string $location, string $item)
    {
        $l = $this->context($r, $location, 'menu:write', 'menu', true);
        MenuItem::where('public_id', $item)->where('organization_id', $l->organization_id)->whereHas('category', fn ($q) => $q->whereIn('menu_id', $l->menus()->pluck('menus.id')))->firstOrFail();

        return app(MenuController::class)->update($r, $item, app(UpdateMenuItem::class));
    }

    public function deleteItem(Request $r, string $location, string $item)
    {
        $l = $this->context($r, $location, 'menu:write', 'menu', true);
        MenuItem::where('public_id', $item)->where('organization_id', $l->organization_id)->whereHas('category', fn ($q) => $q->whereIn('menu_id', $l->menus()->pluck('menus.id')))->firstOrFail();

        return app(MenuController::class)->destroy($r, $item);
    }

    public function promotions(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'promotions:manage', 'orders', true);

        return Promotion::where('organization_id', $l->organization_id)->where('restaurant_id', $l->restaurant_id)->where('location_id', $l->id)->latest('id')->paginate(50);
    }

    public function savePromotion(Request $r, string $location, ?string $promotion = null)
    {
        $l = $this->context($r, $location, 'promotions:manage', 'orders', true);
        $p = $promotion ? Promotion::where('public_id', $promotion)->where('restaurant_id', $l->restaurant_id)->where('organization_id', $l->organization_id)->where('location_id', $l->id)->firstOrFail() : null;

        return response()->json(app(ManagePromotion::class)->execute($r->user(), $l, $r->all(), $p), $p ? 200 : 201);
    }

    public function analytics(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'analytics:read');
        $d = $r->validate(['days' => 'required|integer|in:7,30,90']);
        $data = app(RestaurantOverview::class)->build($r->user(), $l, $d['days']);
        foreach (['orders' => 'orders:read', 'reservations' => 'reservations:read', 'menu' => 'menu:read', 'visits' => 'menu:read', 'tables' => 'tables:read'] as $key => $scope) {
            if (! $r->user()->tokenCan($scope)) {
                $data[$key] = null;
            }
        }
        if ($data['tables']) {
            if (! $r->user()->tokenCan('orders:read')) {
                $data['tables']['top'] = [];
            } if (! $r->user()->tokenCan('reservations:read')) {
                unset($data['tables']['reserved']);
            }
        }

        return response()->json($data);
    }

    public function events(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'events:read');
        $d = $r->validate(['after' => 'nullable|ulid', 'limit' => 'nullable|integer|between:1,100']);
        $types = [];
        foreach (['orders:read' => ['orders', 'order.created', 'order.updated'], 'reservations:read' => ['reservations', 'reservation.created', 'reservation.updated'], 'menu:read' => ['menu', 'menu.updated', 'item.updated'], 'promotions:manage' => ['orders', 'promotion.updated']] as $ability => $group) {
            if ($r->user()->tokenCan($ability) && app(TenantAccess::class)->canFeature($r->user(), $l, array_shift($group))) {
                $types = [...$types, ...$group];
            }
        }
        $rows = OutboxMessage::where('organization_id', $l->organization_id)->where('location_id', $l->id)->whereIn('type', $types)->when($d['after'] ?? null, fn ($q, $after) => $q->where('event_id', '>', $after))->orderBy('event_id')->limit($d['limit'] ?? 100)->get(['event_id', 'type', 'payload', 'created_at']);

        return response()->json(['events' => $rows, 'next_cursor' => $rows->last()?->event_id ?? ($d['after'] ?? null), 'delivery_semantics' => 'at_least_once']);
    }

    public function callback(Request $r, string $location)
    {
        $l = $this->context($r, $location, 'webhooks:manage');
        $d = $r->validate(['url' => 'required|url:https|max:2048', 'enabled' => 'required|boolean', 'events' => 'required|array|min:1|max:7', 'events.*' => ['required', 'distinct', Rule::in(['order.created', 'order.updated', 'reservation.created', 'reservation.updated', 'menu.updated', 'item.updated', 'promotion.updated'])]]);
        $map = ['order' => ['orders:read', 'orders'], 'reservation' => ['reservations:read', 'reservations'], 'menu' => ['menu:read', 'menu'], 'item' => ['menu:read', 'menu'], 'promotion' => ['promotions:manage', 'orders']];
        foreach ($d['events'] as $event) {
            [$ability, $feature] = $map[explode('.', $event)[0]];
            app(WordpressAccess::class)->ability($r, $ability);
            app(TenantAccess::class)->requireFeature($r->user(), $l, $feature);
        }
        $c = app(WordpressAccess::class)->connection($r);
        $endpoint = WebhookEndpoint::where('wordpress_connection_id', $c->id)->where('location_id', $l->id)->first();
        $endpoint = app(ManageWebhook::class)->execute($r->user(), $l, [...$d, 'name' => 'WordPress: '.$c->name, 'secret' => $c->webhook_secret], $endpoint);
        $endpoint->update(['wordpress_connection_id' => $c->id]);

        return response()->json($endpoint);
    }
}
