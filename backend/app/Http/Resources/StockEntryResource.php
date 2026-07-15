<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier?->trade_name ?? $this->supplier?->corporate_name),
            'nfe_number' => $this->nfe_number,
            'nfe_series' => $this->nfe_series,
            'nfe_key' => $this->nfe_key,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'freight_value' => $this->freight_value,
            'products_total' => $this->products_total,
            'total_value' => $this->total_value,
            'generated_accounts_payable_id' => $this->generated_accounts_payable_id,
            'generated_accounts_payable' => AccountsPayableResource::make($this->whenLoaded('generatedAccountsPayable')),
            'items' => StockMovementResource::collection($this->whenLoaded('stockMovements')),
            'imported_by' => $this->imported_by,
            'created_at' => $this->created_at,
        ];
    }
}
