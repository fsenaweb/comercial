<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versão enxuta de UserResource (só id/nome) para telas onde qualquer papel
 * precisa escolher um usuário (ex.: seletor de vendedor no PDV) sem expor
 * e-mail/comissão/papel de colegas — isso fica reservado à tela
 * "Usuários e Permissões", admin-only (ver UserPolicy::viewAny).
 */
class UserOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
