<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variation_id' => $this->product_variation_id,
            'product_name' => $this->whenLoaded('productVariation', fn () => $this->productVariation?->product?->name),
            'product_code' => $this->whenLoaded('productVariation', fn () => $this->productVariation?->code),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'origin' => $this->origin,
            'reference_id' => $this->reference_id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'created_at' => $this->created_at,
        ];
    }
}
