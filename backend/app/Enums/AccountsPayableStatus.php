<?php

namespace App\Enums;

enum AccountsPayableStatus: string
{
    case Open = 'open';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Em aberto',
            self::Paid => 'Quitado',
        };
    }
}
