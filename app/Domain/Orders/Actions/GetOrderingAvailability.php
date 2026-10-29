<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Dining\OrderingWindow;
use App\Domain\Orders\LocationSchedule;
use App\Models\DiningTable;
use App\Models\RestaurantLocation;
use Illuminate\Validation\ValidationException;

class GetOrderingAvailability
{
    public function execute(RestaurantLocation $location, ?DiningTable $table = null): array
    {
        $schedule = app(LocationSchedule::class)->status($location);
        $guard = (bool) ($location->preferences['block_unavailable_online_orders'] ?? true);
        if ($table) {
            abort_unless((int) $table->location_id === $location->id && (int) $table->organization_id === $location->organization_id, 404);
        }
        $reason = 'open';
        $deadline = $schedule['closes_at'];
        if (! $location->accepting_orders) {
            $reason = 'ordering_paused';
        } elseif ($table) {
            $window = app(OrderingWindow::class)->status($location);
            $deadline = $window['deadline_at'];
            if (! $location->fulfillment?->dine_in_enabled) {
                $reason = 'service_disabled';
            } elseif (! $table->is_active || $table->ordering_paused) {
                $reason = 'table_closed';
            } elseif (! $window['is_open']) {
                $reason = $window['reason'];
            }
        } elseif (! ($location->fulfillment?->pickup_enabled || $location->fulfillment?->delivery_enabled)) {
            $reason = 'service_disabled';
        } elseif (! $schedule['is_open']) {
            $reason = 'outside_hours';
        } elseif ($location->fulfillment?->dine_in_enabled && ! DiningTable::where('location_id', $location->id)->where('organization_id', $location->organization_id)->where('is_active', true)->where('ordering_paused', false)->exists()) {
            $reason = 'no_open_tables';
        }

        $canAdd = $reason === 'open' || (! $table && ! $guard && in_array($reason, ['outside_hours', 'no_open_tables'], true));

        return ['schedule' => $schedule, 'can_order' => $reason === 'open', 'can_add_to_cart' => $canAdd, 'reason' => $reason, 'guard_enabled' => $table ? true : $guard, 'mode' => $table ? 'table' : 'online', 'deadline_at' => $deadline];
    }

    public function ensureOnlineOpen(RestaurantLocation $location): void
    {
        $status = $this->execute($location);
        if (! $status['can_order']) {
            throw ValidationException::withMessages(['checkout' => match ($status['reason']) {
                'outside_hours' => __('This location is closed.'),
                'no_open_tables' => __('Online orders are paused because no table is open at this location.'),
                'service_disabled' => __('This fulfillment method is unavailable.'),
                default => __('This location is not accepting orders.'),
            }]);
        }
    }
}
