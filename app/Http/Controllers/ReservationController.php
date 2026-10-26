<?php

namespace App\Http\Controllers;

use App\Domain\Reservations\Availability;
use App\Domain\Reservations\CreateReservation;
use App\Domain\Reservations\TransitionReservation;
use App\Domain\Tenancy\TenantAccess;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\RestaurantLocation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReservationController extends Controller
{
    private function publicBooking(Request $request, string $id): Reservation
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) === 64, 404);
        $booking = Reservation::where('public_id', $id)->where('token_hash', hash('sha256', $token))->firstOrFail();
        $mapped = $request->attributes->get('storefront_location');
        abort_if($mapped && (int) $mapped !== $booking->location_id, 404);

        return $booking;
    }

    public function form(Request $request, string $location, PublicOrderingController $ordering)
    {
        $context = $ordering->resolve($request, $location);
        abort_unless($context->fulfillment?->reservations_enabled, 404);

        return Inertia::render('storefront/Reserve', ['today' => now($context->timezone)->format('Y-m-d'), 'location' => $context->only('public_id', 'name', 'timezone'), 'restaurant' => $context->restaurant->only('name', 'primary_color', 'logo_path'), 'rules' => array_intersect_key($context->setup_profile['reservations'] ?? [], array_flip(['min_party_size', 'max_party_size', 'advance_days', 'guest_notes', 'duration_minutes', 'confirmation', 'hold_minutes']))]);
    }

    public function available(Request $request, string $location, PublicOrderingController $ordering)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'party_size' => 'required|integer|between:1,100']);
        $context = $ordering->resolve($request, $location);
        abort_unless($context->fulfillment?->reservations_enabled, 404);
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], $context->timezone);
        $days = $context->setup_profile['reservations']['advance_days'] ?? 30;
        abort_unless($day->betweenIncluded(now($context->timezone)->startOfDay(), now($context->timezone)->addDays($days)->endOfDay()), 422);
        $slots = [];
        $engine = app(Availability::class);
        $interval = $context->setup_profile['reservations']['interval_minutes'] ?? 30;
        $engine->ensureEnabled($context);
        $bookings = Reservation::where('location_id', $context->id)->blocking()->where('starts_at', '<', $day->addDay()->utc())->where('ends_at', '>', $day->utc())->get();
        $tables = DiningTable::where('location_id', $context->id)->where('is_active', true)->get();
        for ($minute = 0; $minute < 1440; $minute += $interval) {
            $candidate = $day->addMinutes($minute)->format('Y-m-d\TH:i');
            try {
                [$start, $end] = $engine->interval($context, $candidate, (int) $data['party_size']);
                $engine->allocate($context, $start, $end, (int) $data['party_size'], $bookings, $tables);
                $slots[] = $candidate;
            } catch (ValidationException) {
            }
        }

        return response()->json(['slots' => array_values(array_unique($slots)), 'timezone' => $context->timezone])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, string $location, PublicOrderingController $ordering, CreateReservation $action)
    {
        $result = $action->execute($ordering->resolve($request, $location), $request->all(), (string) $request->header('Idempotency-Key'));

        return response()->json(['reservation_id' => $result['reservation']->public_id, 'token' => $result['token'], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201)->header('Cache-Control', 'no-store');
    }

    public function track(Request $request, string $reservation)
    {
        $booking = $this->publicBooking($request, $reservation);
        $status = $booking->status === 'pending' && $booking->hold_expires_at?->isPast() ? 'expired' : $booking->status;

        return response()->json([...$booking->only('public_id', 'party_size', 'starts_at', 'ends_at', 'timezone', 'version', 'hold_expires_at', 'cancellation_deadline'), 'status' => $status, 'restaurant' => $booking->location->restaurant->name, 'can_cancel' => $status === 'pending' || ($status === 'confirmed' && ! $booking->cancellation_deadline->isPast())])->header('Cache-Control', 'no-store');
    }

    public function cancel(Request $request, string $reservation, TransitionReservation $action)
    {
        $data = $request->validate(['version' => 'required|integer|min:1']);
        $action->execute($this->publicBooking($request, $reservation), 'cancelled', $data['version']);

        return $this->track($request, $reservation);
    }

    public function manage(Request $request, string $location, WorkspaceController $workspace)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireOperate($request->user(), $context);
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d', 'status' => 'nullable|in:pending,confirmed,seated,completed,cancelled,rejected,no_show']);
        $date = CarbonImmutable::parse($data['date'] ?? now($context->timezone)->format('Y-m-d'), $context->timezone);
        $query = Reservation::where('location_id', $context->id)->with('table')->where('starts_at', '>=', $date->startOfDay()->utc())->where('starts_at', '<', $date->addDay()->startOfDay()->utc());
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return Inertia::render('restaurant/Reservations', ['location' => $context->load('restaurant', 'hours', 'fulfillment', 'zones'), 'locations' => $workspace->locations($request), 'canManage' => app(TenantAccess::class)->canManage($request->user(), $context->organization_id), 'reservations' => $query->orderBy('starts_at')->paginate(50)->withQueryString(), 'date' => $date->format('Y-m-d')]);
    }

    public function transition(Request $request, string $reservation, TransitionReservation $action)
    {
        $data = $request->validate(['status' => 'required|in:confirmed,rejected,cancelled,seated,completed,no_show', 'version' => 'required|integer|min:1']);
        $action->execute(Reservation::where('public_id', $reservation)->firstOrFail(), $data['status'], $data['version'], $request->user());

        return back()->with('success', __('Reservation updated.'));
    }
}
