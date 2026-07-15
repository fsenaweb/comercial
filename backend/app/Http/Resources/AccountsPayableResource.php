<?php

namespace App\Http\Resources;

use App\Enums\InstallmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountsPayableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier?->trade_name ?? $this->supplier?->corporate_name),
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'installments_count' => $this->installments_count,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'stock_entry_id' => $this->stock_entry_id,
            'has_overdue_installment' => $this->whenLoaded(
                'installments',
                fn () => $this->installments->contains(
                    fn ($installment) => $installment->status === InstallmentStatus::Pending && $installment->due_date?->isPast()
                )
            ),
            'installments' => PayableInstallmentResource::collection($this->whenLoaded('installments')),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
