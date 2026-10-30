<?php

namespace App\Domain\Orders;

use App\Models\Promotion;
use App\Models\RestaurantLocation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ApplyPromotion
{
    public function execute(RestaurantLocation $location, array $input, array $lines, int $subtotal, int $fee, bool $reserve): array
    {
        $code = isset($input['promotion_code']) ? strtoupper(trim($input['promotion_code'])) : null;
        $at = empty($input['scheduled_for']) ? CarbonImmutable::now() : CarbonImmutable::parse($input['scheduled_for'])->utc();
        $query = Promotion::where('organization_id', $location->organization_id)->where('restaurant_id', $location->restaurant_id)->where(fn ($q) => $q->whereNull('location_id')->orWhere('location_id', $location->id))->where('active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
        $code ? $query->where('code', $code) : $query->whereNull('code');
        $best = null;
        $discount = 0;
        foreach ($query->orderBy('id')->lockForUpdate()->get() as $promotion) {
            if (($promotion->usage_limit !== null && $promotion->used_count >= $promotion->usage_limit) || $subtotal < $promotion->minimum_order || ! in_array($input['fulfillment'], $promotion->fulfillments, true)) {
                continue;
            }
            $eligible = 0;
            $units = [];
            foreach ($lines as $line) {
                if ($promotion->product_ids && ! in_array($line['line']['product_public_id'], $promotion->product_ids, true)) {
                    continue;
                }
                $eligible += $line['line']['total'];
                if ($promotion->kind === 'buy_x_get_y') {
                    for ($i = 0; $i < $line['line']['quantity']; $i++) {
                        $units[] = $line['line']['unit_price'];
                    }
                }
            }
            $amount = match ($promotion->kind) {
                'percent' => intdiv($eligible * $promotion->value, 10000),'fixed' => min($eligible, $promotion->value),'free_delivery' => $eligible > 0 ? $fee : 0, default => 0
            };
            if ($promotion->kind === 'buy_x_get_y') {
                sort($units);
                $reward = intdiv(count($units), $promotion->buy_quantity + $promotion->reward_quantity) * $promotion->reward_quantity;
                $amount = array_sum(array_slice($units, 0, $reward));
            }
            $amount = min($amount, $promotion->max_discount ?? PHP_INT_MAX, $subtotal + $fee);
            if ($amount > $discount) {
                $discount = $amount;
                $best = $promotion;
            }
        }
        if ($code && ! $best) {
            throw ValidationException::withMessages(['promotion_code' => __('This promotion is invalid, exhausted or not applicable to the basket.')]);
        }
        if ($best && $reserve) {
            $best->increment('used_count');
        }

        return ['discount' => $discount, 'promotion_snapshot' => $best ? ['public_id' => $best->public_id, 'name' => $best->name, 'code' => $best->code, 'kind' => $best->kind, 'value' => $best->value, 'discount' => $discount] : null];
    }
}
