<?php

namespace App\Domain\Rewards;

use App\Models\Order;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class RewardCards
{
    public function __construct(private RewardCampaigns $campaigns) {}

    public function create(RestaurantLocation $location): string
    {
        abort_unless($this->campaigns->current($location)->exists(), 404);
        $token = Str::random(64);
        DB::table('reward_cards')->insert([
            'organization_id' => $location->organization_id, 'location_id' => $location->id,
            'token_hash' => hash('sha256', $token), 'created_at' => now(),
        ]);

        return $token;
    }

    public function resolve(RestaurantLocation $location, string $token): stdClass
    {
        $card = $this->cards($location)->where('token_hash', hash('sha256', $token))->first();
        if (! $card) {
            throw ValidationException::withMessages(['card' => __('This Rewards card could not be found.')]);
        }

        return $card;
    }

    public function cardCode(RestaurantLocation $location, stdClass $card): array
    {
        return $this->issue($location, $card->id, null);
    }

    public function venueCode(RestaurantLocation $location, string $publicId): array
    {
        $campaign = $this->campaigns->current($location)->where('mode', 'venue_qr')->where('public_id', $publicId)->firstOrFail();

        return $this->issue($location, null, $campaign->id);
    }

    private function issue(RestaurantLocation $location, ?int $card, ?int $campaign): array
    {
        $code = Str::upper(Str::random(12));
        $expires = now()->addMinutes(5);
        DB::table('reward_codes')->insert([
            'location_id' => $location->id, 'card_id' => $card, 'campaign_id' => $campaign,
            'code_hash' => hash('sha256', $code), 'expires_at' => $expires,
        ]);

        return ['code' => $code, 'expires_at' => $expires->toIso8601String()];
    }

    public function join(RestaurantLocation $location, stdClass $card, string $code): int
    {
        return DB::transaction(function () use ($location, $card, $code) {
            $challenge = $this->code($location, $code)->whereNotNull('campaign_id')->lockForUpdate()->first();
            $this->check($challenge && (! $challenge->card_id || (int) $challenge->card_id === (int) $card->id), 'code', __('This Rewards code is unavailable or has expired.'));
            if (! $challenge->card_id) {
                DB::table('reward_codes')->where('id', $challenge->id)->update(['card_id' => $card->id, 'expires_at' => now()->addMinutes(30)]);
            }

            return (int) $challenge->id;
        }, 3);
    }

    public function pending(RestaurantLocation $location)
    {
        return $this->activeCodes($location)->whereNotNull('reward_codes.card_id')->whereNotNull('reward_codes.campaign_id')
            ->join('reward_campaigns', 'reward_campaigns.id', '=', 'reward_codes.campaign_id')
            ->where('reward_campaigns.organization_id', $location->organization_id)
            ->where('reward_campaigns.location_id', $location->id)->orderByDesc('reward_codes.id')->limit(30)
            ->get(['reward_codes.id', 'reward_campaigns.public_id as campaign', 'reward_campaigns.name']);
    }

    public function inspect(RestaurantLocation $location, string $code)
    {
        $challenge = $this->code($location, $code)->whereNull('campaign_id')->whereNotNull('card_id')->first();
        $this->check((bool) $challenge, 'code', __('This Rewards code is unavailable or has expired.'));

        return $this->campaigns->summary($location, $challenge->card_id);
    }

    public function confirm(RestaurantLocation $location, User $actor, array $input): void
    {
        DB::transaction(function () use ($location, $actor, $input) {
            $challenge = isset($input['pending'])
                ? $this->activeCodes($location)->where('id', $input['pending'])->whereNotNull('campaign_id')->whereNotNull('card_id')->lockForUpdate()->first()
                : $this->code($location, $input['code'])->whereNull('campaign_id')->whereNotNull('card_id')->lockForUpdate()->first();
            $this->check((bool) $challenge, 'code', __('This Rewards code is unavailable or has expired.'));
            $card = $this->cards($location)->where('id', $challenge->card_id)->lockForUpdate()->firstOrFail();
            $campaign = $this->campaigns->current($location)->where('public_id', $input['campaign'])->lockForUpdate()->first();
            $expected = isset($input['pending']) ? 'venue_qr' : 'customer_qr';
            $this->check($campaign && $campaign->mode === $expected && (! $challenge->campaign_id || (int) $challenge->campaign_id === (int) $campaign->id), 'campaign', __('This Rewards campaign is unavailable for this action.'));
            $receipt = 'staff:'.hash('sha256', Str::upper(trim($input['receipt'])));
            $this->check($this->credit($campaign, $card->id, $receipt, $input['quantity'], $actor->id), 'receipt', __('This purchase already counted for this campaign.'));
            $this->consume($challenge);
        }, 3);
    }

    public function redeem(RestaurantLocation $location, User $actor, string $code, string $publicId): void
    {
        DB::transaction(function () use ($location, $actor, $code, $publicId) {
            $challenge = $this->code($location, $code)->whereNull('campaign_id')->whereNotNull('card_id')->lockForUpdate()->first();
            $this->check((bool) $challenge, 'code', __('This Rewards code is unavailable or has expired.'));
            $card = $this->cards($location)->where('id', $challenge->card_id)->lockForUpdate()->firstOrFail();
            $campaign = $this->campaigns->query($location)->where('public_id', $publicId)->firstOrFail();
            $balance = $this->balance($campaign->id, $card->id);
            $this->check(RewardCampaigns::available($campaign, $balance->lockForUpdate()->first()) > 0, 'campaign', __('This card has no reward ready to redeem.'));
            $balance->increment('redeemed');
            $this->event($campaign->id, $card->id, 'redeem:'.$challenge->id, 'redeem', $campaign->reward_quantity, $actor->id);
            $this->consume($challenge);
        }, 3);
    }

    // Runs inside the order status transaction. Only server-priced order snapshots count.
    public function awardOrder(Order $order): void
    {
        if (! $order->reward_card_id) {
            return;
        }
        $location = $order->location;
        $card = $this->cards($location)->where('id', $order->reward_card_id)->lockForUpdate()->first();
        if (! $card) {
            return;
        }
        $lines = $order->items()->where('total', '>', 0)->get(['product_public_id', 'quantity']);
        $orderDay = $order->created_at->copy()->setTimezone($location->timezone)->toDateString();
        $campaigns = $this->campaigns->current($location)->where('mode', 'online')->where('active_since', '<=', $order->created_at)
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', $orderDay))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $orderDay))
            ->orderBy('id')->lockForUpdate()->get();
        $freeUnits = collect($order->promotion_snapshot['free_units'] ?? [])->only($lines->pluck('product_public_id')->all());
        foreach ($campaigns as $campaign) {
            $eligible = json_decode($campaign->buy_products, true);
            $quantity = (int) $lines->whereIn('product_public_id', $eligible)->sum('quantity') - $freeUnits->only($eligible)->sum();
            if ($quantity > 0) {
                $this->credit($campaign, $card->id, 'order:'.$order->id, $quantity);
            }
        }
    }

    private function credit(stdClass $campaign, int $card, string $reference, int $quantity, ?int $actor = null): bool
    {
        // The card and campaign are locked, including across two different cards using the same receipt.
        if (DB::table('reward_events')->where('campaign_id', $campaign->id)->where('reference', $reference)->lockForUpdate()->first()) {
            return false;
        }
        $balance = $this->balance($campaign->id, $card);
        if ($balance->increment('earned', $quantity) === 0) {
            DB::table('reward_balances')->insert(['campaign_id' => $campaign->id, 'card_id' => $card, 'earned' => $quantity]);
        }
        $this->event($campaign->id, $card, $reference, 'credit', $quantity, $actor);

        return true;
    }

    private function event(int $campaign, int $card, string $reference, string $kind, int $quantity, ?int $actor): void
    {
        DB::table('reward_events')->insert([
            'campaign_id' => $campaign, 'card_id' => $card, 'reference' => $reference,
            'kind' => $kind, 'quantity' => $quantity, 'actor_id' => $actor, 'created_at' => now(),
        ]);
    }

    private function balance(int $campaign, int $card)
    {
        return DB::table('reward_balances')->where('campaign_id', $campaign)->where('card_id', $card);
    }

    private function cards(RestaurantLocation $location)
    {
        return DB::table('reward_cards')->where('organization_id', $location->organization_id)->where('location_id', $location->id);
    }

    private function activeCodes(RestaurantLocation $location)
    {
        return DB::table('reward_codes')->where('reward_codes.location_id', $location->id)
            ->whereNull('reward_codes.consumed_at')->where('reward_codes.expires_at', '>', now());
    }

    private function code(RestaurantLocation $location, string $code)
    {
        return $this->activeCodes($location)->where('code_hash', hash('sha256', Str::upper($code)));
    }

    private function consume(stdClass $challenge): void
    {
        DB::table('reward_codes')->where('id', $challenge->id)->update(['consumed_at' => now()]);
    }

    private function check(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
