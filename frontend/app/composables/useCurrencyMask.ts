/**
 * Máscara de moeda (BRL). Trabalha só com dígitos - cada dígito digitado
 * empurra os centavos, como em caixas registradoras físicas.
 */
export function useCurrencyMask() {
  function digitsToCents(raw: string): number {
    const digits = raw.replace(/\D/g, '')

    return digits === '' ? 0 : Number.parseInt(digits, 10)
  }

  function format(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    })
  }

  function maskInput(raw: string): string {
    return format(digitsToCents(raw))
  }

  function toNumber(masked: string): number {
    return digitsToCents(masked) / 100
  }

  return { maskInput, toNumber, format, digitsToCents }
}
