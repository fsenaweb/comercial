<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'seller_id' => $this->seller_id,
            'seller_name' => $this->whenLoaded('seller', fn () => $this->seller?->name),
            'cash_register_id' => $this->cash_register_id,
            'subtotal' => $this->subtotal,
            'discount_type' => $this->discount_type->value,
            'discount_type_label' => $this->discount_type->label(),
            'discount_value' => $this->discount_value,
            'discount' => $this->discount,
            'total' => $this->total,
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'canceled_reason' => $this->canceled_reason,
            'canceled_at' => $this->canceled_at,
            'expires_at' => $this->expires_at,
            'converted_to_sale_id' => $this->converted_to_sale_id,
            'converted_to_sale_number' => $this->whenLoaded('convertedToSale', fn () => $this->convertedToSale?->number),
            'origin_quote_id' => $this->whenLoaded('originQuote', fn () => $this->originQuote?->id),
            'origin_quote_number' => $this->whenLoaded('originQuote', fn () => $this->originQuote?->number),
            'converted_at' => $this->converted_at,
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
