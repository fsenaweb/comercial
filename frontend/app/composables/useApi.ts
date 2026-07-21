function readCookie(name: string): string | null {
  if (import.meta.server) return null

  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))

  return match ? decodeURIComponent(match[1] ?? '') : null
}

/**
 * Cliente HTTP único para a API Laravel. Sanctum autentica por cookie de sessão
 * (SPA same-origin em produção, ver docs/01-architecture.md) - por isso toda
 * chamada envia `credentials: 'include'` e o header X-XSRF-TOKEN lido do cookie.
 */
export function useApi() {
  const config = useRuntimeConfig()

  return $fetch.create({
    baseURL: config.public.apiBase,
    credentials: 'include',
    onRequest({ options }) {
      const token = readCookie('XSRF-TOKEN')

      if (token) {
        options.headers = new Headers(options.headers)
        options.headers.set('X-XSRF-TOKEN', token)
      }
    },
  })
}

/**
 * Necessário antes de POST /login: garante o cookie XSRF-TOKEN via Sanctum.
 * Fica fora de /api (rota própria do Sanctum) - ver docker/nginx/default.conf.
 */
export async function ensureCsrfCookie(): Promise<void> {
  const config = useRuntimeConfig()
  const apiBase = config.public.apiBase as string
  const root = apiBase.replace(/\/api\/?$/, '')

  await $fetch('/sanctum/csrf-cookie', { baseURL: root, credentials: 'include' })
}
