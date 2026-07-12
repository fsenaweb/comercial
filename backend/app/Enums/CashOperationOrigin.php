<?php

namespace App\Enums;

enum CashOperationOrigin: string
{
    case Sale = 'sale';
    case CashWithdrawal = 'cash_withdrawal';
    case CashReinforcement = 'cash_reinforcement';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Venda',
            self::CashWithdrawal => 'Sangria',
            self::CashReinforcement => 'Reforço',
            self::Adjustment => 'Ajuste',
        };
    }
}
