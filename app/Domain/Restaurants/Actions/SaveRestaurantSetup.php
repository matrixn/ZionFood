<?php

namespace App\Domain\Restaurants\Actions;

use App\Domain\Restaurants\SetupProgress;
use App\Domain\Restaurants\SyncDiningTables;
use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveRestaurantSetup
{
    public function execute(User $actor, RestaurantLocation $context, string $step, array $data): void
    {
        app(TenantAccess::class)->requireManage($actor, $context->organization_id);
        DB::transaction(function () use ($context, $step, $data) {
            $location = RestaurantLocation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            if ($location->setup_revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => __('The setup was changed in another window. Reload before saving.')]);
            }
            $profile = $location->setup_profile ?? [];
            unset($data['revision']);
            if ($step === 'basics') {
                $location->restaurant()->update(['name' => $data['restaurant_name']]);
                $location->fill(array_intersect_key($data, array_flip(['name', 'address', 'timezone'])));
            }
            if ($step === 'seating' && $data['mode'] === 'tables') {
                $data['capacity'] = array_sum(array_column($data['tables'], 'seats'));
            }
            if ($step === 'seating') {
                app(SyncDiningTables::class)->assertEditable($location);
                if (($location->fulfillment?->dine_in_enabled && ($data['mode'] !== 'tables' || ! count($data['tables']))) || ($location->fulfillment?->reservations_enabled && $data['capacity'] < 1)) {
                    throw ValidationException::withMessages(['capacity' => __('Disable the active seating services before removing their capacity or tables.')]);
                }
                app(SyncDiningTables::class)->execute($location, $data);
            }
            if ($step === 'seating' && ($data['capacity'] > 10000 || (($profile['reservations']['planned'] ?? false) && $data['capacity'] < $profile['reservations']['max_party_size']))) {
                throw ValidationException::withMessages(['capacity' => __('Check the seating capacity and the maximum reservation party size.')]);
            }
            if ($step === 'services') {
                $data['catering_planned'] ??= $profile['services']['catering_planned'] ?? false;
                $data['qr_requires_staff'] ??= $profile['services']['qr_requires_staff'] ?? false;
                if (($data['dine_in_enabled'] ?? false) && (($profile['seating']['mode'] ?? '') !== 'tables' || empty($profile['seating']['tables']))) {
                    throw ValidationException::withMessages(['dine_in_enabled' => __('Configure tables before enabling QR ordering.')]);
                }
                if (($data['reservations_enabled'] ?? false) && (empty($profile['reservations']['planned']) || ($profile['seating']['capacity'] ?? 0) < 1)) {
                    throw ValidationException::withMessages(['reservations_enabled' => __('Configure seating and reservation rules before enabling reservations.')]);
                }
                if (($data['dine_in_enabled'] ?? false) || ($data['reservations_enabled'] ?? false)) {
                    app(SyncDiningTables::class)->execute($location, $profile['seating']);
                }
                $location->fulfillment()->updateOrCreate([], array_intersect_key($data, array_flip(['pickup_enabled', 'delivery_enabled', 'dine_in_enabled', 'reservations_enabled'])));
            }
            if ($step === 'reservations' && $data['planned']) {
                $capacity = $profile['seating']['capacity'] ?? 0;
                if ($capacity < $data['max_party_size']) {
                    throw ValidationException::withMessages(['max_party_size' => __('The maximum party size cannot exceed the configured seating capacity.')]);
                }
            }
            if ($step === 'publish') {
                // Online bookings can be disabled without changing existing confirmed reservations.
                if ($data['accepting_orders'] && ! app(SetupProgress::class)->for($location->fresh())['ready_to_publish']) {
                    throw ValidationException::withMessages(['accepting_orders' => __('Complete the required setup steps before accepting orders.')]);
                }
                $location->accepting_orders = $data['accepting_orders'];
            } else {
                $profile[$step] = $data;
            }
            if ($step === 'reservations' && ! $data['planned']) {
                $location->fulfillment()->update(['reservations_enabled' => false]);
            }
            $location->setup_profile = $profile;
            $location->setup_revision++;
            $location->save();
        }, 3);
    }
}
