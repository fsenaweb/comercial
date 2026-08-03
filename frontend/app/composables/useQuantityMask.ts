/**
 * Máscara de quantidade decimal (suporta fração, ex. venda de meia unidade
 * ou peso em kg). Ao contrário de useCurrencyMask (que empurra centavos
 * dígito a dígito), aqui os dígitos formam a parte inteira normalmente -
 * digitar "1" fica "1"; só entra em modo fracionado quando o usuário digita
 * a vírgula explicitamente (ex. "0,5"). Pedido do cliente, 2026-08-03.
 */
export function useQuantityMask() {
  function sanitize(raw: string): string {
    let value = raw.replace(/[^\d,]/g, '')
    const firstComma = value.indexOf(',')
    if (firstComma !== -1) {
      value = value.slice(0, firstComma + 1) + value.slice(firstComma + 1).replace(/,/g, '')
    }
    return value
  }

  /** Usar no @input - só sanitiza (dígitos + 1 vírgula), sem forçar formato ainda. */
  function maskInput(raw: string): string {
    return sanitize(raw)
  }

  /** Usar no @blur - normaliza pro formato final "N,DD". */
  function formatOnBlur(raw: string): string {
    const sanitized = sanitize(raw)
    const [rawInt = '', rawDec = ''] = sanitized.split(',')
    const intPart = rawInt.replace(/^0+(?=\d)/, '') || '0'
    const decPart = (rawDec + '00').slice(0, 2)

    return `${intPart},${decPart}`
  }

  function toNumber(masked: string): number {
    const sanitized = sanitize(masked)
    const [rawInt = '0', rawDec = ''] = sanitized.split(',')
    const decPart = (rawDec + '00').slice(0, 2)

    return Number(`${rawInt || '0'}.${decPart}`)
  }

  return { maskInput, formatOnBlur, toNumber }
}
