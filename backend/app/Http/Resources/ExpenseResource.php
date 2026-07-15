<?php

namespace App\Http\Resources;

use App\Enums\ExpenseStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'category' => $this->category,
            'amount' => $this->amount,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_overdue' => $this->status === ExpenseStatus::Pending && $this->due_date?->isPast(),
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
