<?php

namespace App\Domain\Reservations;

use App\Models\DiningTable;
use App\Models\OrganizationSubscription;
use App\Models\Reservation;
use App\Models\RestaurantLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class Availability
{
    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['reservation' => __($message)]);
    }

    public function ensureEnabled(RestaurantLocation $location): void
    {
        if (! $location->fulfillment?->reservations_enabled) {
            $this->fail('Reservations are currently disabled.');
        }
        $subscription = OrganizationSubscription::with('plan')->where('organization_id', $location->organization_id)->first();
        if (! $subscription || ! ($subscription->plan->features['ordering'] ?? false) || ! ($subscription->status === 'active' || ($subscription->status === 'trial' && $subscription->trial_ends_at?->isFuture()))) {
            $this->fail('Online reservations are unavailable.');
        }
    }

    public function interval(RestaurantLocation $location, string $localTime, int $party): array
    {
        $rules = $location->setup_profile['reservations'] ?? [];
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $localTime, $location->timezone);
        } catch (\Throwable) {
            $this->fail('Choose a valid reservation time.');
        }
        if ($start->format('Y-m-d\TH:i') !== $localTime) {
            $this->fail('Choose a valid reservation time.');
        }
        $now = CarbonImmutable::now($location->timezone);
        $end = $start->addMinutes($rules['duration_minutes'] ?? 90);
        if ($start->lt($now->addMinutes($rules['minimum_notice_minutes'] ?? 60)) || $start->gt($now->addDays($rules['advance_days'] ?? 30))) {
            $this->fail('The reservation time is outside the allowed booking window.');
        }
        if ($party < ($rules['min_party_size'] ?? 1) || $party > ($rules['max_party_size'] ?? 8)) {
            $this->fail('The party size is outside the allowed range.');
        }
        if (((int) $start->format('i')) % ($rules['interval_minutes'] ?? 30) !== 0) {
            $this->fail('Choose an available arrival interval.');
        }
        $hours = $location->loadMissing('hours')->hours->firstWhere('weekday', $start->isoWeekday());
        if (! $hours || ! $start->isSameDay($end) || $start->format('H:i:s') < $hours->opens_at || $end->format('H:i:s') > $hours->closes_at) {
            $this->fail('The reservation must fit within restaurant opening hours.');
        }

        return [$start->utc(), $end->utc(), $rules];
    }

    public function allocate(RestaurantLocation $location, CarbonImmutable $start, CarbonImmutable $end, int $party, ?Collection $bookings = null, ?Collection $tables = null): ?DiningTable
    {
        $existing = $bookings?->filter(fn ($booking) => $booking->starts_at->lt($end) && $booking->ends_at->gt($start)) ?? Reservation::where('location_id', $location->id)->blocking()->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get();
        $events = [[$start->timestamp, $party], [$end->timestamp, -$party]];
        foreach ($existing as $booking) {
            $events[] = [max($start->timestamp, $booking->starts_at->timestamp), $booking->party_size];
            $events[] = [min($end->timestamp, $booking->ends_at->timestamp), -$booking->party_size];
        }
        usort($events, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $used = 0;
        $capacity = $location->setup_profile['seating']['capacity'] ?? 0;
        foreach ($events as [, $delta]) {
            $used += $delta;
            if ($used > $capacity) {
                $this->fail('No capacity is available for this time.');
            }
        }
        if (($location->setup_profile['seating']['mode'] ?? 'capacity') !== 'tables') {
            return null;
        }
        $table = $tables !== null
            ? $tables->filter(fn ($table) => $table->seats >= $party && ! $existing->pluck('dining_table_id')->contains($table->id))->sortBy([['seats', 'asc'], ['id', 'asc']])->first()
            : DiningTable::where('location_id', $location->id)->where('is_active', true)->where('seats', '>=', $party)->whereNotIn('id', $existing->pluck('dining_table_id')->filter())->orderBy('seats')->orderBy('id')->first();
        if (! $table) {
            $this->fail('No table is available for this party size and time.');
        }

        return $table;
    }
}
