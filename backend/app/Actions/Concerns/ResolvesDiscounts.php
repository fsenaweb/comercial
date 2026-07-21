<?php

namespace App\Actions\Concerns;

use App\Enums\DiscountType;

trait ResolvesDiscounts
{
    /**
     * Acima disso, o desconto (por item ou da venda) só é aceito com a senha
     * de um usuário admin — decisão do cliente (2026-07-21), pra evitar que
     * caixa/vendedor conceda descontos grandes sem o dono saber.
     */
    private const MAX_DISCOUNT_PERCENT = '20';

    /**
     * Verdadeiro quando `$discountAmount` passa de MAX_DISCOUNT_PERCENT% de `$base`
     * — usado tanto pro desconto de item quanto pro desconto total da venda, sempre
     * comparando contra o valor bruto (antes do desconto em questão), nunca contra o
     * total já descontado.
     */
    private function discountExceedsCap(string $base, string $discountAmount): bool
    {
        if (bccomp($base, '0', 2) <= 0) {
            return false;
        }

        return bccomp(bcmul($discountAmount, '100', 4), bcmul($base, self::MAX_DISCOUNT_PERCENT, 4), 4) > 0;
    }

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
