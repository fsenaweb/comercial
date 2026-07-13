<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variation_id' => $this->product_variation_id,
            'product_name' => $this->whenLoaded('productVariation', fn () => $this->productVariation?->product?->name),
            'product_code' => $this->whenLoaded('productVariation', fn () => $this->productVariation?->product_code),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_type' => $this->discount_type->value,
            'discount_type_label' => $this->discount_type->label(),
            'discount_value' => $this->discount_value,
            'discount' => $this->discount,
            'total' => $this->total,
            'is_wholesale' => $this->is_wholesale,
        ];
    }
}
