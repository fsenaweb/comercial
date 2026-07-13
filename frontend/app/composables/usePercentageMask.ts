/**
 * Máscara de percentual (0-100, até 2 casas decimais). Mesmo espírito de
 * useCurrencyMask (trabalha por dígitos), usada no desconto percentual do PDV.
 */
export function usePercentageMask() {
  function digitsToValue(raw: string): number {
    const digits = raw.replace(/\D/g, '')
    if (digits === '') {
      return 0
    }

    const value = Number.parseInt(digits, 10) / 100

    return Math.min(100, value)
  }

  function format(value: number): string {
    return `${value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`
  }

  function maskInput(raw: string): string {
    return format(digitsToValue(raw))
  }

  function toNumber(masked: string): number {
    return digitsToValue(masked)
  }

  return { maskInput, toNumber, format, digitsToValue }
}
