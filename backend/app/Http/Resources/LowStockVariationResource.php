<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LowStockVariationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name,
            'color' => $this->color,
            'size' => $this->size,
            'code' => $this->code,
            'current_quantity' => $this->current_quantity,
            'min_quantity' => $this->min_quantity,
        ];
    }
}
