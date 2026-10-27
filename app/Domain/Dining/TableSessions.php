<?php

namespace App\Domain\Dining;

use App\Domain\Tenancy\TenantAccess;
use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableSessions
{
    public function bulk(User $actor, RestaurantLocation $context, string $operation, ?string $area = null): int
    {
        app(TenantAccess::class)->requireFeature($actor, $context, 'tables', true);
        abort_unless(in_array($operation, ['open', 'close'], true), 422);

        return DB::transaction(function () use ($actor, $context, $operation, $area) {
            $location = RestaurantLocation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            if ($operation === 'open' && ! $location->fulfillment?->dine_in_enabled) {
                throw ValidationException::withMessages(['tables' => __('Table ordering is disabled at this location. Please contact the restaurant team.')]);
            }
            $tables = DiningTable::where('location_id', $location->id)->where('is_active', true)->when($area !== null, fn ($q) => $q->where('area', $area))->orderBy('id')->get();
            if ($tables->isEmpty()) {
                throw ValidationException::withMessages(['tables' => __('No active tables match the selected area.')]);
            }
            foreach ($tables as $table) {
                if ($operation === 'open') {
                    $this->open($table, $actor);
                } else {
                    $this->close($actor, $table);
                }
            }

            return $tables->count();
        }, 3);
    }

    public function open(DiningTable $context, ?User $actor = null, ?string $qrToken = null): DiningSession
    {
        if ($actor) {
            app(TenantAccess::class)->requireFeature($actor, $context->location, 'tables', true);
        }

        return DB::transaction(function () use ($context, $actor, $qrToken) {
            $location = RestaurantLocation::whereKey($context->location_id)->lockForUpdate()->firstOrFail();
            $table = DiningTable::whereKey($context->id)->firstOrFail();
            if ($qrToken !== null) {
                abort_unless(hash_equals($table->qr_token_hash, hash('sha256', $qrToken)), 404);
            }
            abort_unless($location->fulfillment?->dine_in_enabled && $table->is_active, 404);
            if ($actor) {
                $table->update(['ordering_paused' => false]);
            } else {
                if ($table->ordering_paused) {
                    throw ValidationException::withMessages(['table' => __('The menu for this table is closed by the restaurant team.')]);
                }
                app(OrderingWindow::class)->ensureOpen($location);
            }
            $session = $table->sessions()->whereNull('closed_at')->where('expires_at', '>', now())->latest()->first();
            if ($session) {
                return $session;
            }
            if (! $actor && ($location->setup_profile['services']['qr_requires_staff'] ?? false)) {
                throw ValidationException::withMessages(['table' => __('Ask the restaurant team to open this table before ordering.')]);
            }
            $token = Str::random(64);

            return DiningSession::create(['public_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'dining_table_id' => $table->id, 'token_hash' => hash('sha256', $token), 'token_encrypted' => Crypt::encryptString($token), 'expires_at' => now()->addHours(8), 'opened_by' => $actor?->id]);
        }, 3);
    }

    public function close(User $actor, DiningTable $context): void
    {
        app(TenantAccess::class)->requireFeature($actor, $context->location, 'tables', true);
        DB::transaction(function () use ($context) {
            RestaurantLocation::whereKey($context->location_id)->lockForUpdate()->firstOrFail();
            DiningTable::whereKey($context->id)->update(['ordering_paused' => true]);
            $context->sessions()->whereNull('closed_at')->update(['closed_at' => now()]);
        }, 3);
    }

    public function rotate(User $actor, DiningTable $context): void
    {
        app(TenantAccess::class)->requireFeature($actor, $context->location, 'tables', true);
        DB::transaction(function () use ($context) {
            RestaurantLocation::whereKey($context->location_id)->lockForUpdate()->firstOrFail();
            $token = Str::random(64);
            $context->update(['qr_token_hash' => hash('sha256', $token), 'qr_token_encrypted' => Crypt::encryptString($token)]);
            $context->sessions()->whereNull('closed_at')->update(['closed_at' => now()]);
        }, 3);
    }
}
