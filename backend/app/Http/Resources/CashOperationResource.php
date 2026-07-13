<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cash_register_id' => $this->cash_register_id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'origin' => $this->origin->value,
            'origin_label' => $this->origin->label(),
            'reference_id' => $this->reference_id,
            'payment_method_id' => $this->payment_method_id,
            'payment_method_name' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod?->name),
            'amount' => $this->amount,
            'notes' => $this->notes,
            'sale_number' => $this->whenLoaded('sale', fn () => $this->sale?->number),
            'sale_status' => $this->whenLoaded('sale', fn () => $this->sale?->status?->value),
            'sale_status_label' => $this->whenLoaded('sale', fn () => $this->sale?->status?->label()),
            'created_at' => $this->created_at,
        ];
    }
}
