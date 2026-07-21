export type DiscountType = 'fixed' | 'percentage'

export interface CartLineInput {
  unitPrice: number
  quantity: number
  discountType: DiscountType
  discountValue: number
}

function toCents(value: number): number {
  return Math.round(value * 100)
}

/**
 * Resolve o valor absoluto (em centavos) do desconto sobre uma base, espelhando
 * o resolveDiscountAmount() do RegisterSaleAction: fixo usa o valor direto,
 * percentual multiplica e trunca (nunca arredonda pra cima) no final — decisão
 * do usuário (2026-07-19): quando o desconto exato cai em fração de centavo,
 * o comerciante fica com o valor cheio, nunca o cliente. Precisa truncar
 * igual aqui e no backend, senão o total mostrado no PDV diverge do total
 * que o backend recalcula na hora de registrar a venda.
 */
function discountAmountCents(baseCents: number, type: DiscountType, value: number): number {
  if (type === 'percentage') {
    return Math.floor((baseCents * value) / 100)
  }

  return toCents(value)
}

export function lineTotalCents(line: CartLineInput): number {
  const gross = toCents(line.unitPrice) * line.quantity
  const discount = discountAmountCents(gross, line.discountType, line.discountValue)

  return Math.max(0, gross - discount)
}

export function subtotalCents(lines: CartLineInput[]): number {
  return lines.reduce((sum, line) => sum + lineTotalCents(line), 0)
}

export function saleTotalCents(subtotal: number, discountType: DiscountType, discountValue: number): number {
  const discount = discountAmountCents(subtotal, discountType, discountValue)

  return Math.max(0, subtotal - discount)
}
