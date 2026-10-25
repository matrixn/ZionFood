<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'public_id' => $this->public_id, 'status' => $this->status->value, 'version' => $this->version,
            'fulfillment' => $this->fulfillment, 'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone, 'delivery_address' => $this->delivery_address, 'notes' => $this->notes,
            'subtotal' => $this->subtotal, 'delivery_fee' => $this->delivery_fee, 'total' => $this->total, 'currency' => $this->currency,
            'created_at' => $this->created_at->toIso8601String(), 'items' => $this->whenLoaded('items'), 'history' => $this->whenLoaded('history'),
            'transitions' => array_map(fn ($s) => $s->value, $this->status->transitions($this->fulfillment)),
        ];
    }
}
