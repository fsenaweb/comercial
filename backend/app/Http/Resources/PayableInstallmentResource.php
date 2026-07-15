<?php

namespace App\Http\Resources;

use App\Enums\InstallmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayableInstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accounts_payable_id' => $this->accounts_payable_id,
            'number' => $this->number,
            'amount' => $this->amount,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_overdue' => $this->status === InstallmentStatus::Pending && $this->due_date?->isPast(),
            'paid_at' => $this->paid_at,
            'paid_amount' => $this->paid_amount,
            'notes' => $this->notes,
        ];
    }
}
