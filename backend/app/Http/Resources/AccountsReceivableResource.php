<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountsReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'balance' => $this->balance(),
            'last_entry_at' => $this->whenLoaded('entries', fn () => $this->entries->max('created_at')),
            'entries' => AccountEntryResource::collection($this->whenLoaded('entries')),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
