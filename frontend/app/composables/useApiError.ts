interface ApiErrorPayload {
  message: string
  errors?: Record<string, string[]>
}

/**
 * Normaliza o formato de erro padronizado da API (ver docs/02-design-patterns.md,
 * seção "Tratamento de erros") para uso direto nas telas.
 */
export function useApiError() {
  function parse(error: unknown): ApiErrorPayload {
    const data = (error as { data?: ApiErrorPayload })?.data

    if (data?.message) {
      return data
    }

    return { message: 'Não foi possível completar a operação. Tente novamente.' }
  }

  function firstFieldError(error: unknown, field: string): string | null {
    const parsed = parse(error)

    return parsed.errors?.[field]?.[0] ?? null
  }

  return { parse, firstFieldError }
}
