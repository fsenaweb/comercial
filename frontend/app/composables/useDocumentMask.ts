/**
 * Máscara de CPF/CNPJ. O tipo é explícito (não adivinhado pelo tamanho) —
 * cada campo trava no formato certo (11 dígitos pra CPF, 14 pra CNPJ) em vez
 * de virar CNPJ sozinho quando o usuário continua digitando um CPF longo.
 */
export function useDocumentMask() {
  function maskInput(raw: string, type: 'cpf' | 'cnpj' = 'cpf'): string {
    const maxDigits = type === 'cnpj' ? 14 : 11
    const digits = raw.replace(/\D/g, '').slice(0, maxDigits)

    if (type === 'cpf') {
      return digits
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
    }

    return digits
      .replace(/(\d{2})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1/$2')
      .replace(/(\d{4})(\d{1,2})$/, '$1-$2')
  }

  return { maskInput }
}
