<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Seller = 'seller';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cashier => 'Caixa',
            self::Seller => 'Vendedor',
        };
    }
}
