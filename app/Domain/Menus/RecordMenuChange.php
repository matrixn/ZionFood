<?php

namespace App\Domain\Menus;

use App\Models\OutboxMessage;
use App\Models\RestaurantLocation;
use Illuminate\Support\Str;

class RecordMenuChange
{
    public function execute(int $organization, ?int $menuId = null, array $itemData = []): void
    {
        $locations = RestaurantLocation::where('organization_id', $organization)->when($menuId, fn ($q) => $q->whereHas('menus', fn ($m) => $m->where('menus.id', $menuId)))->get();
        foreach ($locations as $location) {
            OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $organization, 'location_id' => $location->id, 'type' => $itemData ? 'item.updated' : 'menu.updated', 'payload' => ['location_id' => $location->public_id, ...$itemData]]);
        }
    }
}
