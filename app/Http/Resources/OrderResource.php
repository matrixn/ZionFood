<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'public_id' => $this->public_id, 'status' => $this->status->value, 'version' => $this->version,
            'fulfillment' => $this->fulfillment, 'customer_name' => $this->customer_name, 'table_code' => $this->table_code, 'table_area' => $this->table_area,
            'customer_phone' => $this->customer_phone, 'delivery_address' => $this->delivery_address, 'notes' => $this->notes,
            'delivery_latitude' => $this->delivery_latitude, 'delivery_longitude' => $this->delivery_longitude,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(), 'preparation_at' => $this->preparation_at?->toIso8601String(), 'discount' => $this->discount, 'promotion' => $this->promotion_snapshot,
            'subtotal' => $this->subtotal, 'delivery_fee' => $this->delivery_fee, 'total' => $this->total, 'currency' => $this->currency,
            'created_at' => $this->created_at->toIso8601String(), 'items' => $this->whenLoaded('items'), 'history' => $this->whenLoaded('history'),
            'transitions' => array_values(array_map(fn ($s) => $s->value, array_filter($this->status->transitions($this->fulfillment), fn ($s) => ! $this->preparation_at?->isFuture() || in_array($s->value, ['accepted', 'cancelled', 'rejected'], true)))),
        ];
    }
}
