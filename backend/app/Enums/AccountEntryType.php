<?php

namespace App\Enums;

enum AccountEntryType: string
{
    case Purchase = 'purchase';
    case Payment = 'payment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Payment => 'Pagamento',
        };
    }
}
