<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'color' => $this->color,
            'size' => $this->size,
            'ean_gtin' => $this->ean_gtin,
            'code' => $this->code,
            'reference' => $this->reference,
            'cost_price' => $this->cost_price,
            'markup' => $this->markup,
            'sale_price' => $this->sale_price,
            'current_quantity' => $this->current_quantity,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'wholesale_min_qty' => $this->wholesale_min_qty,
            'wholesale_price' => $this->wholesale_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
