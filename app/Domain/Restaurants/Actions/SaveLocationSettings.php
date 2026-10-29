<?php

namespace App\Domain\Restaurants\Actions;

use App\Domain\Restaurants\AuthorizeOrderingChanges;
use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveLocationSettings
{
    public function execute(User $actor, RestaurantLocation $context, array $data): void
    {
        DB::transaction(function () use ($actor, $context, $data) {
            $location = RestaurantLocation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            app(TenantAccess::class)->requireManage($actor, $location->organization_id);
            app(AuthorizeOrderingChanges::class)->execute($actor, $location, $data);
            if (isset($data['dine_in_cutoff_minutes']) && (int) $data['dine_in_cutoff_minutes'] !== (int) $location->fulfillment?->dine_in_cutoff_minutes) {
                app(TenantAccess::class)->requireFeature($actor, $location, 'tables', true);
            }
            $location->update(array_intersect_key($data, array_flip(['name', 'address', 'accepting_orders'])));
            $location->fulfillment()->updateOrCreate(['location_id' => $location->id], array_intersect_key($data, array_flip(['pickup_enabled', 'delivery_enabled', 'dine_in_cutoff_minutes'])));
            $location->hours()->delete();
            foreach ($data['hours'] as $hours) {
                $location->hours()->create(array_intersect_key($hours, array_flip(['weekday', 'opens_at', 'closes_at'])));
            }
            $location->zones()->delete();
            foreach ($data['zones'] as $zone) {
                $zone['postal_prefix'] ??= '';
                if (isset($zone['geometry'])) {
                    $zone['geometry'] = array_intersect_key($zone['geometry'], array_flip(['type', 'coordinates']));
                }
                $location->zones()->create(array_intersect_key($zone, array_flip(['name', 'postal_prefix', 'fee', 'minimum_order', 'geometry', 'priority'])));
            }
        });
    }
}
