<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'corporate_name' => $this->corporate_name,
            'trade_name' => $this->trade_name,
            'mobile_phone' => $this->mobile_phone,
            'phone' => $this->phone,
            'email' => $this->email,
            'document' => $this->document,
            'is_company' => $this->is_company,
            'state_registration' => $this->state_registration,
            'address' => $this->address,
            'zip_code' => $this->zip_code,
            'address_number' => $this->address_number,
            'address_complement' => $this->address_complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
