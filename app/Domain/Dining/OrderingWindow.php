<?php

namespace App\Domain\Dining;

use App\Models\RestaurantLocation;
use Illuminate\Validation\ValidationException;

class OrderingWindow
{
    public function ensureOpen(RestaurantLocation $location): void
    {
        $now = now($location->timezone);
        $hours = $location->hours()->where('weekday', $now->isoWeekday())->first();
        if (! $hours || $now->format('H:i:s') < $hours->opens_at || $now->greaterThanOrEqualTo($now->copy()->setTimeFromTimeString($hours->closes_at)->subMinutes($location->fulfillment?->dine_in_cutoff_minutes ?? 30))) {
            throw ValidationException::withMessages(['table' => __('Table ordering has closed for today. Please contact the restaurant team.')]);
        }
    }
}
