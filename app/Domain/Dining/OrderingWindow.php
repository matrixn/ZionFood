<?php

namespace App\Domain\Dining;

use App\Domain\Orders\LocationSchedule;
use App\Models\RestaurantLocation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class OrderingWindow
{
    public function status(RestaurantLocation $location): array
    {
        $schedule = app(LocationSchedule::class)->status($location);
        $now = CarbonImmutable::parse($schedule['checked_at']);
        $open = $schedule['opens_at'] ? CarbonImmutable::parse($schedule['opens_at']) : null;
        $close = $schedule['closes_at'] ? CarbonImmutable::parse($schedule['closes_at']) : null;
        $cutoff = $close?->subMinutes($location->fulfillment?->dine_in_cutoff_minutes ?? 30);
        $reason = ! $schedule['is_open'] ? 'outside_hours' : ($now->gte($cutoff) ? 'cutoff' : 'open');

        return ['is_open' => $reason === 'open', 'reason' => $reason, 'opens_at' => $open?->format('H:i'), 'closes_at' => $close?->format('H:i'), 'cutoff_at' => $cutoff?->format('H:i'), 'deadline_at' => $cutoff?->toIso8601String(), 'timezone' => $location->timezone, 'checked_at' => $now->toIso8601String()];
    }

    public function ensureOpen(RestaurantLocation $location): void
    {
        if (! $this->status($location)['is_open']) {
            throw ValidationException::withMessages(['table' => __('Table ordering has closed for today. Please contact the restaurant team.')]);
        }
    }
}
