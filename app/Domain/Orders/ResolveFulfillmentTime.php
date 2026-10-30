<?php

namespace App\Domain\Orders;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantLocation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ResolveFulfillmentTime
{
    private function require(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['scheduled_for' => __($message)]);
        }
    }

    public function execute(RestaurantLocation $location, array $input, bool $capacity = true): array
    {
        $settings = app(CommerceSettings::class)->values($location);
        $booking = null;
        if (! empty($input['reservation_id'])) {
            $this->require($settings['reservation_preorders_enabled'] && $input['fulfillment'] === 'dine_in', 'Reservation preorders are unavailable.');
            $booking = Reservation::where('public_id', $input['reservation_id'])->where('organization_id', $location->organization_id)->where('location_id', $location->id)->where('token_hash', hash('sha256', $input['reservation_token'] ?? ''))->first();
            $this->require($booking && $booking->status === 'confirmed', 'Use a confirmed reservation and its private token.');
            $this->require(! Order::where('reservation_id', $booking->id)->whereNotIn('status', ['cancelled', 'rejected'])->exists(), 'This reservation already has an active preorder.');
            $at = $booking->starts_at;
            if (! empty($input['scheduled_for'])) {
                $this->require(CarbonImmutable::parse($input['scheduled_for'])->equalTo($at), 'The preorder time must match the reservation.');
            }
        } elseif (! empty($input['scheduled_for'])) {
            $this->require($settings['scheduled_orders_enabled'] && in_array($input['fulfillment'], ['pickup', 'delivery'], true), 'Scheduled orders are unavailable.');
            $at = CarbonImmutable::parse($input['scheduled_for']);
        } else {
            return ['scheduled_for' => null, 'preparation_at' => null, 'reservation' => null];
        }
        $at = $at->setTimezone($location->timezone);
        $this->require($at->gte(now()->addMinutes($settings['scheduling_notice_minutes'])) && $at->lte(now()->addDays($settings['scheduling_days'])), 'Choose a future time within the allowed scheduling window.');
        if (! $booking) {
            $this->require((int) $at->format('i') % 15 === 0 && $at->second === 0, 'Choose a fifteen-minute scheduling slot.');
        }
        $location->loadMissing('hours', 'fulfillment');
        $row = $location->hours->firstWhere('weekday', $at->isoWeekday());
        $this->require($row && $at->gte($at->setTimeFromTimeString($row->opens_at)) && $at->lt($at->setTimeFromTimeString($row->closes_at)), 'The chosen time is outside opening hours.');
        $this->require($location->accepting_orders && (bool) $location->fulfillment?->getAttribute($input['fulfillment'].'_enabled'), 'This fulfillment method is unavailable.');
        if ($capacity && $settings['scheduled_orders_per_slot'] > 0) {
            $this->require(Order::where('location_id', $location->id)->where('scheduled_for', $at->utc())->whereNotIn('status', ['cancelled', 'rejected'])->count() < $settings['scheduled_orders_per_slot'], 'The chosen scheduling slot is full.');
        }

        return ['scheduled_for' => $at->utc(), 'preparation_at' => $at->utc()->subMinutes($settings['preparation_minutes'])->max(CarbonImmutable::now()), 'reservation' => $booking];
    }

    public function slots(RestaurantLocation $location, string $date, string $fulfillment): array
    {
        $location = $location->fresh();
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $location->timezone);
        $this->require($day->gte(now($location->timezone)->startOfDay()) && $day->lte(now($location->timezone)->addDays(app(CommerceSettings::class)->values($location)['scheduling_days'])->startOfDay()), 'Choose a future time within the allowed scheduling window.');
        $orders = Order::where('location_id', $location->id)->whereNotIn('status', ['cancelled', 'rejected'])->whereBetween('scheduled_for', [$day->utc(), $day->addDay()->utc()])->selectRaw('scheduled_for, COUNT(*) as total')->groupBy('scheduled_for')->get()->mapWithKeys(fn ($r) => [CarbonImmutable::parse($r->scheduled_for, 'UTC')->timestamp => $r->total]);
        $limit = app(CommerceSettings::class)->values($location)['scheduled_orders_per_slot'];
        $slots = [];
        for ($at = $day->utc(); $at->lt($day->addDay()->utc()); $at = $at->addMinutes(15)) {
            if ($limit && ($orders[$at->timestamp] ?? 0) >= $limit) {
                continue;
            }
            try {
                $this->execute($location, ['fulfillment' => $fulfillment, 'scheduled_for' => $at->toIso8601String()], false);
                $slots[] = $at->setTimezone($location->timezone)->toIso8601String();
            } catch (ValidationException) {
            }
        }

        return $slots;
    }
}
