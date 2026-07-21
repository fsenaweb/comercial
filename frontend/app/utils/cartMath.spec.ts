import { describe, expect, it } from 'vitest'
import { lineTotalCents, saleTotalCents, subtotalCents } from './cartMath'

describe('cartMath', () => {
  it('computes a line total with a fixed discount', () => {
    expect(lineTotalCents({ unitPrice: 10, quantity: 3, discountType: 'fixed', discountValue: 5 })).toBe(2500)
  })

  it('computes a line total with a percentage discount', () => {
    // 3 x R$12,50 = R$37,50; 10% = R$3,75 -> R$33,75
    expect(lineTotalCents({ unitPrice: 12.5, quantity: 3, discountType: 'percentage', discountValue: 10 })).toBe(3375)
  })

  it('clamps a fixed discount larger than the line total to zero', () => {
    expect(lineTotalCents({ unitPrice: 10, quantity: 1, discountType: 'fixed', discountValue: 50 })).toBe(0)
  })

  it('sums multiple lines into a subtotal', () => {
    const lines = [
      { unitPrice: 10, quantity: 2, discountType: 'fixed' as const, discountValue: 0 },
      { unitPrice: 5, quantity: 1, discountType: 'fixed' as const, discountValue: 0 },
    ]

    expect(subtotalCents(lines)).toBe(2500)
  })

  it('applies a fixed discount to the sale total', () => {
    expect(saleTotalCents(3000, 'fixed', 2)).toBe(2800)
  })

  it('applies a percentage discount to the sale total', () => {
    expect(saleTotalCents(3000, 'percentage', 10)).toBe(2700)
  })

  it('clamps the sale total discount to zero', () => {
    expect(saleTotalCents(1000, 'fixed', 50)).toBe(0)
  })

  it('truncates a percentage discount landing on a half-cent, in favor of the merchant', () => {
    // 15% de R$12,90 (1290 centavos) = R$1,935 de desconto - trunca pra
    // R$1,93 (193 centavos), nunca arredonda pra R$1,94, espelhando
    // ResolvesDiscounts::resolveDiscountAmount do backend (decisão do
    // usuário, 2026-07-19: a fração de centavo fica sempre com a loja).
    expect(saleTotalCents(1290, 'percentage', 15)).toBe(1097)
  })
})
