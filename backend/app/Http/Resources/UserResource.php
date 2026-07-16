<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'commission_percent' => $this->commission_percent,
            'active' => $this->active,
            'theme' => $this->theme->value,
            'font_scale' => $this->font_scale->value,
        ];
    }
}
