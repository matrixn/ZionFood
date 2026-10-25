<?php

namespace App\Domain\Orders\Actions;

use App\Http\Requests\CheckoutRequest;
use App\Models\CheckoutIdempotencyKey;
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
        $data['items'] = array_map(fn ($line) => array_intersect_key($line, array_flip(['product_id', 'quantity', 'variant_id', 'modifier_ids'])), $data['items']);
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
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Idempotency key was used with different checkout data.');
                $order = Order::findOrFail($existing->order_id);

                return ['order' => $order, 'tracking_token' => Crypt::decryptString($order->tracking_token_encrypted), 'replayed' => true];
            }
            $this->checkAvailability($location, $data['fulfillment']);
            $lines = [];
            $subtotal = 0;
            $menuIds = $location->menus()->where('is_active', true)->pluck('menus.id');
            foreach ($data['items'] as $requested) {
                $item = MenuItem::with(['variants', 'modifierGroups.options'])->where('public_id', $requested['product_id'])->where('organization_id', $location->organization_id)
                    ->whereHas('category', fn ($q) => $q->whereIn('menu_id', $menuIds))->where('is_available', true)->first();
                $this->require((bool) $item, 'A product is unavailable at this location.');
                $variant = null;
                if ($item->variants->isNotEmpty()) {
                    $variant = $item->variants->firstWhere('id', $requested['variant_id'] ?? null);
                    $this->require($variant && $variant->is_available, 'Select an available variant.');
                } else {
                    $this->require(empty($requested['variant_id']), 'Invalid variant.');
                }
                $unit = $variant?->price ?? $item->price;
                $optionIds = $requested['modifier_ids'] ?? [];
                $this->require(count($optionIds) === count(array_unique($optionIds)), 'Duplicate modifiers are not allowed.');
                $modifiers = [];
                $matched = [];
                foreach ($item->modifierGroups as $group) {
                    $options = $group->options->whereIn('id', $optionIds);
                    $this->require($options->count() >= $group->min_choices && $options->count() <= $group->max_choices, 'Invalid choices for '.$group->name.'.');
                    foreach ($options as $option) {
                        $this->require($option->is_available, 'A modifier is unavailable.');
                        $matched[] = $option->id;
                        $unit += $option->price;
                        $modifiers[] = ['group_name' => $group->name, 'name' => $option->name, 'price' => $option->price];
                    }
                }
                $this->require(count($matched) === count($optionIds), 'Invalid modifier selection.');
                $total = $unit * $requested['quantity'];
                $subtotal += $total;
                $this->require($subtotal <= 100000000, 'Order value exceeds the supported limit.');
                $lines[] = ['line' => ['product_public_id' => $item->public_id, 'name' => $item->name, 'variant_name' => $variant?->name, 'quantity' => $requested['quantity'], 'unit_price' => $unit, 'tax_basis_points' => $item->tax_basis_points, 'total' => $total], 'modifiers' => $modifiers];
            }
            $fee = 0;
            if ($data['fulfillment'] === 'delivery') {
                $zone = $location->zones()->whereKey($data['delivery_zone_id'] ?? 0)->where('is_active', true)->first();
                $this->require($zone && str_starts_with($data['postal_code'] ?? '', $zone->postal_prefix), 'Address is outside the selected delivery zone.');
                $this->require($subtotal >= $zone->minimum_order, 'Delivery minimum order has not been reached.');
                $fee = $zone->fee;
            }
            $token = Str::random(64);
            $order = Order::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'tracking_token_hash' => hash('sha256', $token), 'tracking_token_encrypted' => Crypt::encryptString($token), 'fulfillment' => $data['fulfillment'], 'customer_name' => $data['customer_name'], 'customer_phone' => $data['customer_phone'], 'delivery_address' => $data['delivery_address'] ?? null, 'notes' => $data['notes'] ?? null, 'currency' => $location->currency, 'subtotal' => $subtotal, 'delivery_fee' => $fee, 'total' => $subtotal + $fee]);
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
        $this->require($location->accepting_orders, 'This location is not accepting orders.');
        $subscription = OrganizationSubscription::with('plan')->where('organization_id', $location->organization_id)->first();
        $this->require($subscription && ($subscription->status === 'active' || ($subscription->status === 'trial' && $subscription->trial_ends_at->isFuture())) && ($subscription->plan->features['ordering'] ?? false), 'Online ordering is unavailable.');
        $now = now($location->timezone);
        $hours = $location->hours()->where('weekday', $now->isoWeekday())->first();
        $this->require($hours && $now->format('H:i:s') >= $hours->opens_at && $now->format('H:i:s') < $hours->closes_at, 'This location is closed.');
        $this->require((bool) $location->fulfillment?->getAttribute($fulfillment.'_enabled'), 'This fulfillment method is unavailable.');
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['checkout' => $message]);
        }
    }
}
