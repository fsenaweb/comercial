import { describe, expect, it } from 'vitest'
import { useInstallmentSplit } from './useInstallmentSplit'

describe('useInstallmentSplit', () => {
  const { split, sum } = useInstallmentSplit()

  it('splits a total equally across N installments', () => {
    const installments = split(300, 3, '2026-08-01')
    expect(installments).toHaveLength(3)
    expect(installments.map((i) => i.amount)).toEqual([100, 100, 100])
  })

  it('puts the rounding remainder on the last installment', () => {
    const installments = split(100, 3, '2026-08-01')
    expect(installments.map((i) => i.amount)).toEqual([33.33, 33.33, 33.34])
    expect(sum(installments)).toBe(100)
  })

  it('spaces due dates by the interval, defaulting to 30 days', () => {
    const installments = split(200, 2, '2026-08-01')
    expect(installments[0]?.due_date).toBe('2026-08-01')
    expect(installments[1]?.due_date).toBe('2026-08-31')
  })

  it('returns an empty array for zero installments', () => {
    expect(split(100, 0, '2026-08-01')).toEqual([])
  })
})
