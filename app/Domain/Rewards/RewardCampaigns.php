<?php

namespace App\Domain\Rewards;

use App\Models\MenuItem;
use App\Models\RestaurantLocation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class RewardCampaigns
{
    public function query(RestaurantLocation $location): Builder
    {
        return DB::table('reward_campaigns')->where('organization_id', $location->organization_id)->where('location_id', $location->id);
    }

    public function products(RestaurantLocation $location)
    {
        $menus = $location->menus()->where('menus.organization_id', $location->organization_id)->select('menus.id');

        return MenuItem::where('organization_id', $location->organization_id)
            ->whereHas('category', fn ($query) => $query->whereIn('menu_id', $menus));
    }

    public function current(RestaurantLocation $location): Builder
    {
        $today = now($location->timezone)->toDateString();

        return $this->query($location)->where('active', true)
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today));
    }

    public function save(RestaurantLocation $location, array $input, ?string $publicId): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'active' => ['required', 'boolean'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', ...(! empty($input['starts_on']) ? ['after_or_equal:starts_on'] : [])],
        ];
        if (! $publicId) {
            $rules += [
                'mode' => ['required', 'in:customer_qr,venue_qr,online'],
                'buy_products' => ['required', 'array', 'list', 'min:1', 'max:50'],
                'buy_products.*' => ['required', 'ulid', 'distinct:ignore_case'],
                'buy_quantity' => ['required', 'integer', 'between:1,100'],
                'reward_product' => ['required', 'ulid'],
                'reward_quantity' => ['required', 'integer', 'between:1,50'],
            ];
        }
        $values = Validator::make($input, $rules)->validate();
        if (! $publicId) {
            $values['buy_products'] = array_map(Str::upper(...), $values['buy_products']);
            $values['reward_product'] = Str::upper($values['reward_product']);
        }
        DB::transaction(function () use ($location, $values, $publicId) {
            RestaurantLocation::whereKey($location->id)->lockForUpdate()->firstOrFail();
            if ($publicId) {
                $campaign = $this->query($location)->where('public_id', $publicId)->lockForUpdate()->first();
                abort_unless($campaign, 404);
                $activeSince = $values['active'] ? ($campaign->active_since ?? now()) : null;
                $this->query($location)->where('id', $campaign->id)->update([...$values, 'active_since' => $activeSince, 'updated_at' => now()]);

                return;
            }
            if ($this->query($location)->count() >= 100) {
                throw ValidationException::withMessages(['name' => __('This location has reached the campaign limit.')]);
            }
            $ids = array_unique([...$values['buy_products'], $values['reward_product']]);
            $products = $this->products($location)->whereIn('public_id', $ids)->pluck('name', 'public_id');
            if ($products->count() !== count($ids)) {
                throw ValidationException::withMessages(['buy_products' => __('Choose products from this location’s menu.')]);
            }
            $this->query($location)->insert([
                ...$values, 'public_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id,
                'location_id' => $location->id, 'buy_products' => json_encode($values['buy_products'], JSON_THROW_ON_ERROR),
                'active_since' => $values['active'] ? now() : null, 'reward_name' => $products[$values['reward_product']], 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function summary(RestaurantLocation $location, ?int $card = null, bool $staff = false): Collection
    {
        $current = $this->current($location)->pluck('id');
        $balances = $card ? DB::table('reward_balances')->where('card_id', $card)->get()->keyBy('campaign_id') : collect();
        $campaigns = $this->query($location)->when(! $staff, fn ($query) => $query->whereIn('id', $current->merge($balances->keys())->unique()));
        $campaigns = $campaigns->orderByDesc('id')->get();
        $products = $this->products($location)->whereIn('public_id', $campaigns->flatMap(fn ($c) => json_decode($c->buy_products, true))->unique())->pluck('name', 'public_id');

        return $campaigns->map(function ($campaign) use ($current, $balances, $products) {
            $ids = json_decode($campaign->buy_products, true);
            $balance = $balances->get($campaign->id);
            $earned = (int) ($balance->earned ?? 0);
            $redeemed = (int) ($balance->redeemed ?? 0);

            return [
                'public_id' => $campaign->public_id, 'name' => $campaign->name, 'mode' => $campaign->mode,
                'active' => (bool) $campaign->active, 'current' => $current->contains($campaign->id),
                'starts_on' => $campaign->starts_on, 'ends_on' => $campaign->ends_on,
                'buy_products' => collect($ids)->map(fn ($id) => $products->get($id))->filter()->values(),
                'buy_quantity' => (int) $campaign->buy_quantity, 'reward_name' => $campaign->reward_name,
                'reward_quantity' => (int) $campaign->reward_quantity, 'progress' => $earned % $campaign->buy_quantity,
                'available' => self::available($campaign, $balance), 'redeemed' => $redeemed,
            ];
        });
    }

    public static function available(stdClass $campaign, ?stdClass $balance): int
    {
        return intdiv((int) ($balance->earned ?? 0), $campaign->buy_quantity) - (int) ($balance->redeemed ?? 0);
    }
}
