<?php

namespace App\Domain\Dining;

use App\Models\RestaurantLocation;
use Illuminate\Validation\ValidationException;

class OrderingWindow
{
    public function status(RestaurantLocation $location): array
    {
        $now = now($location->timezone);
        $hours = $location->hours()->where('weekday', $now->isoWeekday())->first();
        $open = $hours ? $now->copy()->setTimeFromTimeString($hours->opens_at) : null;
        $close = $hours ? $now->copy()->setTimeFromTimeString($hours->closes_at) : null;
        $cutoff = $close?->copy()->subMinutes($location->fulfillment?->dine_in_cutoff_minutes ?? 30);
        $reason = ! $hours || $now->lt($open) || $now->gte($close) ? 'outside_hours' : ($now->gte($cutoff) ? 'cutoff' : 'open');

        return ['is_open' => $reason === 'open', 'reason' => $reason, 'opens_at' => $open?->format('H:i'), 'closes_at' => $close?->format('H:i'), 'cutoff_at' => $cutoff?->format('H:i'), 'timezone' => $location->timezone, 'checked_at' => $now->toIso8601String()];
    }

    public function ensureOpen(RestaurantLocation $location): void
    {
        if (! $this->status($location)['is_open']) {
            throw ValidationException::withMessages(['table' => __('Table ordering has closed for today. Please contact the restaurant team.')]);
        }
    }
}
