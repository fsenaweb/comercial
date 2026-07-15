<?php

namespace App\Actions\Concerns;

use App\Enums\DiscountType;

trait ResolvesDiscounts
{
    /**
     * Resolve o valor absoluto do desconto a partir do tipo escolhido pelo
     * operador: fixo usa o valor direto, percentual multiplica e só
     * arredonda pra 2 casas no final (evita erro de arredondamento
     * intermediário). Compartilhado entre venda (`BuildsSaleItems`) e
     * crediário itemizado — mesma fórmula, mesmo bcmath.
     */
    private function resolveDiscountAmount(string $base, DiscountType $type, string $value): string
    {
        if ($type === DiscountType::Percentage) {
            return bcdiv(bcmul($base, $value, 4), '100', 2);
        }

        return bcadd($value, '0', 2);
    }
}
