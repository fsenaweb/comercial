<?php

namespace App\Actions\Concerns;

use Illuminate\Validation\ValidationException;

trait SplitsInstallments
{
    /**
     * Divide um valor total em N parcelas iguais (bcmath), jogando a sobra
     * de centavos da divisão na última parcela — nunca deixa arredondamento
     * "sumir" nem "sobrar" dinheiro entre as parcelas.
     *
     * @return list<string>
     */
    private function splitAmountEqually(string $totalAmount, int $installmentsCount): array
    {
        $base = bcdiv($totalAmount, (string) $installmentsCount, 2);
        $amounts = array_fill(0, $installmentsCount, $base);

        $sumExceptLast = bcmul($base, (string) ($installmentsCount - 1), 2);
        $amounts[$installmentsCount - 1] = bcsub($totalAmount, $sumExceptLast, 2);

        return $amounts;
    }

    /**
     * As parcelas sempre chegam já resolvidas (número/valor/vencimento) do
     * request — o frontend mostra uma sugestão editável e o operador pode
     * ajustar antes de salvar. Aqui só validamos que a soma bate com o
     * valor total, nunca recalculamos por cima do que foi enviado.
     */
    private function assertInstallmentsMatchTotal(array $installments, string $totalAmount): void
    {
        $sum = array_reduce(
            $installments,
            fn ($carry, $installment) => bcadd($carry, (string) $installment['amount'], 2),
            '0.00'
        );

        if (bccomp($sum, $totalAmount, 2) !== 0) {
            throw ValidationException::withMessages([
                'installments' => 'A soma das parcelas precisa ser igual ao valor total.',
            ]);
        }
    }
}
