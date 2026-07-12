/**
 * Máscara de CEP (00000-000).
 */
export function useCepMask() {
  function maskInput(raw: string): string {
    const digits = raw.replace(/\D/g, '').slice(0, 8)

    return digits.replace(/(\d{5})(\d{1,3})$/, '$1-$2')
  }

  return { maskInput }
}
