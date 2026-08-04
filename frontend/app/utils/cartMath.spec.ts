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

  it('computes a line total with a fractional quantity', () => {
    // 0,5 x R$21,24 = R$10,62 (bug motivador: metade de unidade de eletrodo)
    expect(lineTotalCents({ unitPrice: 21.24, quantity: 0.5, discountType: 'fixed', discountValue: 0 })).toBe(1062)
  })

  it('truncates a fractional quantity landing on a fraction of a cent, matching the backend', () => {
    // 0,5 x R$10,99 = R$5,495 - trunca pra R$5,49 (549 centavos), igual
    // bcmul($unitPrice, $quantity, 2) no backend (BuildsSaleItems), que
    // trunca em vez de arredondar. Antes o front arredondava pra R$5,50
    // (550) enquanto o backend gravava R$5,49 - o pagamento em dinheiro
    // batido no valor exibido no PDV era rejeitado na hora de finalizar a
    // venda ("valor não bate"), achado real do cliente (2026-08-04), mais
    // frequente com produto fracionado.
    const result = lineTotalCents({ unitPrice: 10.99, quantity: 0.5, discountType: 'fixed', discountValue: 0 })
    expect(Number.isInteger(result)).toBe(true)
    expect(result).toBe(549)
  })

  it('does not truncate an exact cent value due to floating point imprecision', () => {
    // 0,5 x R$21,24 = R$10,62 exato - garante que o epsilon de proteção não
    // deixa um valor exato cair pro centavo de baixo por erro de ponto
    // flutuante (0,5 x 2124 pode virar 1061.9999999998 em vez de 1062).
    expect(lineTotalCents({ unitPrice: 21.24, quantity: 0.5, discountType: 'fixed', discountValue: 0 })).toBe(1062)
  })

  it('treats a negative fixed "discount" as a markup, adding instead of subtracting', () => {
    // Acréscimo de R$5 num item de R$10 - "desconto" negativo soma em vez de
    // subtrair, sem precisar de um tipo novo (decisão do cliente, 2026-08-03).
    expect(lineTotalCents({ unitPrice: 10, quantity: 1, discountType: 'fixed', discountValue: -5 })).toBe(1500)
  })

  it('treats a negative percentage "discount" as a markup on the sale total', () => {
    // 10% de acréscimo em R$30 -> R$33
    expect(saleTotalCents(3000, 'percentage', -10)).toBe(3300)
  })
})
