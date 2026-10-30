<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Delivery\Polygon;
use App\Domain\Menus\EffectiveModifiers;
use App\Domain\Orders\ApplyPromotion;
use App\Http\Requests\CheckoutRequest;
use App\Models\MenuItem;
use App\Models\RestaurantLocation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PriceOrder
{
    public function execute(RestaurantLocation $location, array $input, bool $reservePromotion = false): array
    {
        $rules = CheckoutRequest::constraints($input);
        foreach (['customer_name', 'customer_phone', 'table_session_token', 'reservation_id', 'reservation_token', 'notes'] as $key) {
            unset($rules[$key]);
        }
        $data = Validator::make($input, $rules)->validate();
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
        $promotion = app(ApplyPromotion::class)->execute($location, $data, $lines, $subtotal, $fee, $reservePromotion);

        return ['lines' => $lines, 'subtotal' => $subtotal, 'delivery_fee' => $fee, 'total' => $subtotal + $fee - $promotion['discount'], ...$promotion];
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['checkout' => $message]);
        }
    }
}
