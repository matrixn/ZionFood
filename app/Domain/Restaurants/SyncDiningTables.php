<?php

namespace App\Domain\Restaurants;

use App\Models\DiningSession;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\RestaurantLocation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncDiningTables
{
    public function assertEditable(RestaurantLocation $location): void
    {
        if (Reservation::where('location_id', $location->id)->blocking()->where('ends_at', '>', now())->exists() || DiningSession::where('location_id', $location->id)->whereNull('closed_at')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['capacity' => __('Seating cannot be changed while future reservations or open table sessions exist.')]);
        }
    }

    public function execute(RestaurantLocation $location, array $seating): void
    {
        $codes = [];
        if (($seating['mode'] ?? '') === 'tables') {
            foreach ($seating['tables'] as $row) {
                $codes[] = $row['code'];
                $table = DiningTable::firstOrNew(['location_id' => $location->id, 'code' => $row['code']]);
                if (! $table->exists) {
                    $token = Str::random(64);
                    $table->fill(['public_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'qr_token_hash' => hash('sha256', $token), 'qr_token_encrypted' => Crypt::encryptString($token)]);
                }
                $table->fill(['area' => $row['area'], 'seats' => $row['seats'], 'is_active' => true])->save();
            }
        }
        DiningTable::where('location_id', $location->id)->whereNotIn('code', $codes)->update(['is_active' => false]);
    }
}
