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
 * percentual multiplica e trunca (nunca arredonda pra cima) no final - decisão
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
  // Trunca (não arredonda) pro centavo, igual o backend faz em
  // BuildsSaleItems::buildSaleItems() com bcmul($unitPrice, $quantity, 2) -
  // bcmath sempre trunca, nunca arredonda. Com quantidade fracionada,
  // unitPrice × quantidade pode cair numa fração de centavo (ex. R$7,99 ×
  // 1,375 = R$10,98625) - se o front arredondasse pra R$10,99 e o backend
  // truncasse pra R$10,98, o pagamento em dinheiro batido no total exibido
  // seria rejeitado na hora de registrar a venda ("valor não bate"). O
  // +1e-7 protege contra erro de ponto flutuante empurrando um valor exato
  // (ex. 1062.0) pra baixo do inteiro por uma fração de bilionésimo.
  const gross = Math.floor(toCents(line.unitPrice) * line.quantity + 1e-7)
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
