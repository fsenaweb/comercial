<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opened_at' => $this->opened_at,
            'opening_amount' => $this->opening_amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'closed_at' => $this->closed_at,
            'closing_amount' => $this->closing_amount,
            'expected_amount' => $this->expectedAmount(),
            'difference_amount' => $this->closing_amount !== null
                ? bcsub((string) $this->closing_amount, $this->expectedAmount(), 2)
                : null,
            'opened_by' => $this->opened_by,
            'opened_by_name' => $this->whenLoaded('openedBy', fn () => $this->openedBy?->name),
            'closed_by' => $this->closed_by,
            'closed_by_name' => $this->whenLoaded('closedBy', fn () => $this->closedBy?->name),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
