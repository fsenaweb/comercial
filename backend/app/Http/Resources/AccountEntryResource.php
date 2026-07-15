<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accounts_receivable_id' => $this->accounts_receivable_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'subtotal' => $this->subtotal,
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value,
            'discount' => $this->discount,
            'amount' => $this->amount,
            'description' => $this->description,
            'items' => AccountEntryItemResource::collection($this->whenLoaded('items')),
            'payment_method_id' => $this->payment_method_id,
            'payment_method_name' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod?->name),
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->created_at,
        ];
    }
}
