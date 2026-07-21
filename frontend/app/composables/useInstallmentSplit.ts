export interface SplitInstallment {
  number: number
  amount: number
  due_date: string
}

/**
 * Sugestão inicial de divisão de parcelas - espelha (em JS, só pra preview)
 * o bcmath do backend (Actions/Concerns/SplitsInstallments.php): divide em
 * partes iguais arredondadas a centavos e joga a sobra na última parcela.
 * O operador pode editar cada linha depois; o backend sempre revalida a
 * soma contra o total antes de salvar.
 */
export function useInstallmentSplit() {
  function split(totalAmount: number, installmentsCount: number, firstDueDate: string, intervalDays = 30): SplitInstallment[] {
    if (installmentsCount < 1 || !Number.isFinite(totalAmount)) return []

    const totalCents = Math.round(totalAmount * 100)
    const baseCents = Math.floor(totalCents / installmentsCount)
    const lastCents = totalCents - baseCents * (installmentsCount - 1)

    const firstDate = new Date(`${firstDueDate}T00:00:00`)

    return Array.from({ length: installmentsCount }, (_, index) => {
      const dueDate = new Date(firstDate)
      dueDate.setDate(dueDate.getDate() + index * intervalDays)

      const cents = index === installmentsCount - 1 ? lastCents : baseCents
      return {
        number: index + 1,
        amount: cents / 100,
        due_date: dueDate.toISOString().slice(0, 10),
      }
    })
  }

  function sum(installments: SplitInstallment[]): number {
    return Math.round(installments.reduce((carry, installment) => carry + installment.amount * 100, 0)) / 100
  }

  return { split, sum }
}
