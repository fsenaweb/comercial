<?php

namespace App\Actions\Concerns;

use App\Enums\DiscountType;

trait ResolvesDiscounts
{
    /**
     * Resolve o valor absoluto do desconto a partir do tipo escolhido pelo
     * operador: fixo usa o valor direto, percentual multiplica e trunca (nunca
     * arredonda pra cima) pra 2 casas no final. Compartilhado entre venda
     * (`BuildsSaleItems`) e crediário itemizado — mesma fórmula, mesmo bcmath.
     *
     * Decisão do usuário (2026-07-19): quando o desconto percentual exato cai
     * em fração de centavo (ex.: 15% de R$12,90 = R$1,935), o valor fica
     * sempre a favor do comerciante — desconto trunca pra baixo (R$1,93), o
     * total a receber nunca é arredondado pra baixo por causa do desconto.
     * `bcdiv`/`bcmul` já truncam por padrão ao reduzir a escala (não é
     * `bcround()`, de propósito). O PDV (`cartMath.ts`) precisa truncar do
     * mesmo jeito, senão o total mostrado diverge do que o backend recalcula
     * na hora de registrar a venda — foi exatamente esse o bug encontrado.
     */
    private function resolveDiscountAmount(string $base, DiscountType $type, string $value): string
    {
        if ($type === DiscountType::Percentage) {
            return bcdiv(bcmul($base, $value, 4), '100', 2);
        }

        return bcadd($value, '0', 2);
    }
}
