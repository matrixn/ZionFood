<?php

namespace App\Domain\Orders;

use App\Domain\Tenancy\TenantAccess;
use App\Models\MenuItem;
use App\Models\OutboxMessage;
use App\Models\Promotion;
use App\Models\RestaurantLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagePromotion
{
    public function execute(User $actor, RestaurantLocation $location, array $input, ?Promotion $context = null): Promotion
    {
        app(TenantAccess::class)->requireFeature($actor, $location, 'orders', true);
        abort_if($context && ((int) $context->restaurant_id !== $location->restaurant_id || (int) $context->location_id !== $location->id), 404);
        $input['code'] = filled($input['code'] ?? null) ? strtoupper(trim($input['code'])) : null;
        $d = Validator::make($input, ['name' => 'required|string|max:100', 'code' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promotions', 'code')->where('restaurant_id', $location->restaurant_id)->ignore($context?->id)], 'kind' => 'required|in:percent,fixed,free_delivery,buy_x_get_y', 'value' => 'required|integer|min:0|max:100000000', 'minimum_order' => 'required|integer|min:0|max:100000000', 'max_discount' => 'nullable|integer|min:1|max:100000000', 'fulfillments' => 'required|array|min:1|max:3', 'fulfillments.*' => 'required|distinct|in:pickup,delivery,dine_in', 'product_ids' => 'nullable|array|max:100', 'product_ids.*' => 'required|ulid|distinct', 'buy_quantity' => 'required|integer|between:1,20', 'reward_quantity' => 'required|integer|between:1,20', 'starts_at' => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/'], 'ends_at' => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', ...(filled($input['starts_at'] ?? null) ? ['after:starts_at'] : [])], 'usage_limit' => 'nullable|integer|min:1|max:1000000', 'active' => 'required|boolean'])->validate();
        foreach (['starts_at', 'ends_at'] as $key) {
            if (filled($d[$key] ?? null)) {
                $d[$key] = CarbonImmutable::parse($d[$key])->utc();
            }
        }
        if (($d['kind'] === 'percent' && ($d['value'] < 1 || $d['value'] > 10000)) || ($d['kind'] === 'fixed' && $d['value'] < 1)) {
            throw ValidationException::withMessages(['value' => __('Use a valid percentage in basis points or an integer amount. The limit is 100 percent.')]);
        }
        if (! empty($d['product_ids'])) {
            abort_unless(MenuItem::where('organization_id', $location->organization_id)->whereHas('category', fn ($q) => $q->whereIn('menu_id', $location->menus()->pluck('menus.id')))->whereIn('public_id', $d['product_ids'])->count() === count($d['product_ids']), 422);
        }

        return DB::transaction(function () use ($d, $context, $location) {
            $promotion = $context ? Promotion::whereKey($context->id)->lockForUpdate()->firstOrFail() : new Promotion(['organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id, 'location_id' => $location->id]);
            $promotion->fill($d)->save();
            OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'type' => 'promotion.updated', 'payload' => ['promotion_id' => $promotion->public_id]]);

            return $promotion;
        });
    }
}
