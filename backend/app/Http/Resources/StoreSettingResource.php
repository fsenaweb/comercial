<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'cnpj' => $this->cnpj,
            'address' => $this->address,
            'phone' => $this->phone,
            'logo_path' => $this->logo_path,
            'require_seller_on_sale' => $this->require_seller_on_sale,
            'auto_open_cash_register' => $this->auto_open_cash_register,
        ];
    }
}
