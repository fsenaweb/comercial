<?php

namespace App\Enums;

enum PrintFormat: string
{
    case Roll80 = 'roll80';
    case Roll58 = 'roll58';
    case A4 = 'a4';

    public function label(): string
    {
        return match ($this) {
            self::Roll80 => 'Bobina 80mm',
            self::Roll58 => 'Bobina 58mm',
            self::A4 => 'Papel A4',
        };
    }
}
