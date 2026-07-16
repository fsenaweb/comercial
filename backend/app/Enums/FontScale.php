<?php

namespace App\Enums;

enum FontScale: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Pequena',
            self::Medium => 'Média',
            self::Large => 'Grande',
        };
    }
}
