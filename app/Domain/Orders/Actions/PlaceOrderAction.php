<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Delivery\Polygon;
use App\Domain\Dining\OrderingWindow;
use App\Domain\Menus\EffectiveModifiers;
use App\Domain\Orders\LocationSchedule;
use App\Http\Requests\CheckoutRequest;
use App\Models\CheckoutIdempotencyKey;
use App\Models\DiningSession;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\OrderStatusHistory;
use App\Models\OrganizationSubscription;
use App\Models\OutboxMessage;
use App\Models\RestaurantLocation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function execute(RestaurantLocation $context, array $input, string $key): array
    {
        $data = Validator::make($input, CheckoutRequest::constraints())->validate();
        Validator::make(['key' => $key], ['key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/']])->validate();
        $data['items'] = array_map(fn ($line) => array_intersect_key($line, array_flip(['product_id', 'quantity', 'variant_id', 'modifier_ids', 'modifiers'])), $data['items']);
        $canonical = function (array $value) use (&$canonical): array {
            if (! array_is_list($value)) {
                ksort($value);
            } foreach ($value as &$entry) {
                if (is_array($entry)) {
                    $entry = $canonical($entry);
                }
            }

            return $value;
        };
        $hash = hash('sha256', json_encode($canonical($data), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($context, $data, $key, $hash) {
            // Serializes checkout per location, including concurrent insertion of a new idempotency key.
            $location = RestaurantLocation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            $existing = CheckoutIdempotencyKey::where('location_id', $location->id)->where('key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, __('Idempotency key was used with different checkout data.'));
                $order = Order::findOrFail($existing->order_id);

                return ['order' => $order, 'tracking_token' => Crypt::decryptString($order->tracking_token_encrypted), 'replayed' => true];
            }
            $this->checkAvailability($location, $data['fulfillment']);
            $session = null;
            if ($data['fulfillment'] === 'dine_in') {
                app(OrderingWindow::class)->ensureOpen($location);
                $session = DiningSession::with('table')->where('location_id', $location->id)->where('organization_id', $location->organization_id)->where('token_hash', hash('sha256', $data['table_session_token']))->whereNull('closed_at')->where('expires_at', '>', now())->first();
                $this->require($session && $session->table->is_active && ! $session->table->ordering_paused, __('The table session has ended. Ask the restaurant team for help.'));
            }
            $lines = [];
            $subtotal = 0;
            $menuIds = $location->menus()->where('is_active', true)->pluck('menus.id');
            foreach ($data['items'] as $requested) {
                $item = MenuItem::with(['variants', 'modifierGroups.options'])->where('public_id', $requested['product_id'])->where('organization_id', $location->organization_id)
                    ->whereHas('category', fn ($q) => $q->whereIn('menu_id', $menuIds))->where('is_available', true)->first();
                $this->require((bool) $item, __('A product is unavailable at this location.'));
                $variant = null;
                if ($item->variants->isNotEmpty()) {
                    $variant = $item->variants->firstWhere('id', $requested['variant_id'] ?? null);
                    $this->require($variant && $variant->is_available, __('Select an available variant.'));
                } else {
                    $this->require(empty($requested['variant_id']), __('Invalid variant.'));
                }
                $this->require($item->order_types === null || in_array($data['fulfillment'], $item->order_types, true), __('This product is unavailable for the selected fulfillment method.'));
                $unit = $variant?->price ?? $item->price;
                $this->require(! isset($requested['modifiers']) || empty($requested['modifier_ids']), __('Use one modifier format only.'));
                $quantities = isset($requested['modifiers']) ? collect($requested['modifiers'])->pluck('quantity', 'id')->all() : array_fill_keys($requested['modifier_ids'] ?? [], 1);
                $optionIds = isset($requested['modifiers']) ? array_column($requested['modifiers'], 'id') : ($requested['modifier_ids'] ?? []);
                $this->require(count($optionIds) === count(array_unique($optionIds)), __('Duplicate modifiers are not allowed.'));
                $modifiers = [];
                $matched = [];
                foreach (app(EffectiveModifiers::class)->groups($item, $variant) as $group) {
                    $options = $group->options->whereIn('id', $optionIds);
                    $this->require(app(EffectiveModifiers::class)->validCount($group, $options->count()), __('Invalid choices for :name.', ['name' => $group->name]));
                    foreach ($options as $option) {
                        $this->require($option->is_available, __('A modifier is unavailable.'));
                        $matched[] = $option->id;
                        $quantity = (int) $quantities[$option->id];
                        $this->require($quantity <= ($group->allow_quantity ? $option->max_quantity : 1), __('Invalid modifier quantity.'));
                        $unit += $option->price * $quantity;
                        $modifiers[] = ['group_name' => $group->name, 'name' => $option->name, 'price' => $option->price, 'quantity' => $quantity, 'kitchen_name' => $option->kitchen_name];
                    }
                }
                $this->require(count($matched) === count($optionIds), __('Invalid modifier selection.'));
                $total = $unit * $requested['quantity'];
                $subtotal += $total;
                $this->require($subtotal <= 100000000, __('Order value exceeds the supported limit.'));
                $lines[] = ['line' => ['product_public_id' => $item->public_id, 'name' => $item->name, 'kitchen_name' => $item->kitchen_name, 'variant_name' => $variant?->name, 'quantity' => $requested['quantity'], 'unit_price' => $unit, 'tax_basis_points' => $item->tax_basis_points, 'total' => $total], 'modifiers' => $modifiers];
            }
            $fee = 0;
            if ($data['fulfillment'] === 'delivery') {
                $zones = $location->zones()->where('is_active', true)->orderBy('priority')->orderBy('id')->get();
                $mappedZones = $zones->whereNotNull('geometry');
                if ($mappedZones->isNotEmpty()) {
                    $this->require(isset($data['delivery_latitude'], $data['delivery_longitude']), __('Select the delivery location on the map.'));
                    $zone = $mappedZones->first(fn ($candidate) => Polygon::contains($candidate->geometry, (float) $data['delivery_latitude'], (float) $data['delivery_longitude']));
                    $this->require((bool) $zone, __('The delivery pin is outside all delivery zones.'));
                } else {
                    $zone = $zones->firstWhere('id', $data['delivery_zone_id'] ?? 0);
                    $this->require($zone && filled($zone->postal_prefix) && str_starts_with($data['postal_code'] ?? '', $zone->postal_prefix), __('Address is outside the selected delivery zone.'));
                }
                $this->require($subtotal >= $zone->minimum_order, __('Delivery minimum order has not been reached.'));
                $fee = $zone->fee;
            }
            $token = Str::random(64);
            $order = Order::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'tracking_token_hash' => hash('sha256', $token), 'tracking_token_encrypted' => Crypt::encryptString($token), 'fulfillment' => $data['fulfillment'], 'customer_name' => $data['customer_name'], 'customer_phone' => $data['customer_phone'], 'delivery_address' => $data['delivery_address'] ?? null, 'notes' => $data['notes'] ?? null, 'currency' => $location->currency, 'subtotal' => $subtotal, 'delivery_fee' => $fee, 'total' => $subtotal + $fee]);
            if ($session) {
                $order->update(['dining_session_id' => $session->id, 'table_code' => $session->table->code, 'table_area' => $session->table->area]);
            }
            if ($data['fulfillment'] === 'delivery' && isset($data['delivery_latitude'], $data['delivery_longitude'])) {
                $order->update(['delivery_latitude' => $data['delivery_latitude'], 'delivery_longitude' => $data['delivery_longitude']]);
            }
            foreach ($lines as $line) {
                $saved = OrderItem::create(['order_id' => $order->id, ...$line['line']]);
                foreach ($line['modifiers'] as $modifier) {
                    OrderItemModifier::create(['order_item_id' => $saved->id, ...$modifier]);
                }
            }
            OrderStatusHistory::create(['order_id' => $order->id, 'to_status' => 'pending']);
            CheckoutIdempotencyKey::create(['location_id' => $location->id, 'key' => $key, 'request_hash' => $hash, 'order_id' => $order->id]);
            OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'type' => 'order.created', 'payload' => ['order_id' => $order->public_id, 'version' => 1]]);

            return ['order' => $order, 'tracking_token' => $token, 'replayed' => false];
        }, 3);
    }

    private function checkAvailability(RestaurantLocation $location, string $fulfillment): void
    {
        $this->require($location->accepting_orders, __('This location is not accepting orders.'));
        $subscription = OrganizationSubscription::with('plan')->where('organization_id', $location->organization_id)->first();
        $this->require($subscription && ($subscription->status === 'active' || ($subscription->status === 'trial' && $subscription->trial_ends_at->isFuture())) && ($subscription->plan->features['ordering'] ?? false), __('Online ordering is unavailable.'));
        if ($fulfillment === 'dine_in') {
            $this->require(app(LocationSchedule::class)->status($location)['is_open'], __('This location is closed.'));
        } else {
            app(GetOrderingAvailability::class)->ensureOnlineOpen($location);
        }
        $this->require((bool) $location->fulfillment?->getAttribute($fulfillment.'_enabled'), __('This fulfillment method is unavailable.'));
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['checkout' => $message]);
        }
    }
}
