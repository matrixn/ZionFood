<?php

namespace App\Domain\Orders;

use App\Models\RestaurantLocation;

class LocationSchedule
{
    public function status(RestaurantLocation $location): array
    {
        $now = now($location->timezone);
        $hours = $location->hours()->get()->keyBy('weekday');
        $today = $hours->get($now->isoWeekday());
        $open = $today ? $now->copy()->setTimeFromTimeString($today->opens_at) : null;
        $close = $today ? $now->copy()->setTimeFromTimeString($today->closes_at) : null;
        $next = null;
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $now->copy()->startOfDay()->addDays($offset);
            $row = $hours->get($date->isoWeekday());
            if ($row) {
                $candidate = $date->setTimeFromTimeString($row->opens_at);
                if ($candidate->gt($now)) {
                    $next = $candidate;
                    break;
                }
            }
        }

        return ['is_open' => $open && $now->gte($open) && $now->lt($close), 'opens_at' => $open?->toIso8601String(), 'closes_at' => $close?->toIso8601String(), 'next_opens_at' => $next?->toIso8601String(), 'checked_at' => $now->toIso8601String(), 'timezone' => $location->timezone];
    }
}
