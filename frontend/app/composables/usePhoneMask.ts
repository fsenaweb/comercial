/**
 * Máscara de telefone. O tipo é explícito (não adivinhado pelo tamanho) -
 * celular sempre trava em 11 dígitos/(XX) XXXXX-XXXX, telefone fixo em 10
 * dígitos/(XX) XXXX-XXXX, sem um "roubar" dígito do outro durante a digitação.
 */
export function usePhoneMask() {
  function maskInput(raw: string, type: 'mobile' | 'landline' = 'mobile'): string {
    const maxDigits = type === 'mobile' ? 11 : 10
    const digits = raw.replace(/\D/g, '').slice(0, maxDigits)

    if (digits.length === 0) return ''
    if (digits.length <= 2) return `(${digits}`

    const area = digits.slice(0, 2)
    const rest = digits.slice(2)
    const numberDigits = type === 'mobile' ? 5 : 4

    if (rest.length <= numberDigits) return `(${area}) ${rest}`

    return `(${area}) ${rest.slice(0, numberDigits)}-${rest.slice(numberDigits)}`
  }

  return { maskInput }
}
