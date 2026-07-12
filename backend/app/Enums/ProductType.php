<?php

namespace App\Enums;

enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';
    case Kit = 'kit';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Produto',
            self::Service => 'Serviço',
            self::Kit => 'Kit',
        };
    }
}
