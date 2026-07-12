/**
 * Cliente CRUD genérico para os endpoints de cadastro (categories, brands...),
 * todos seguindo o mesmo envelope `{ data: ... }` do backend.
 */
export function useResourceApi<T>(path: string) {
  const api = useApi()

  async function list(): Promise<T[]> {
    const res = await api<{ data: T[] }>(`/${path}`)
    return res.data
  }

  async function create(payload: Record<string, unknown>): Promise<T> {
    const res = await api<{ data: T }>(`/${path}`, { method: 'POST', body: payload })
    return res.data
  }

  async function update(id: number, payload: Record<string, unknown>): Promise<T> {
    const res = await api<{ data: T }>(`/${path}/${id}`, { method: 'PUT', body: payload })
    return res.data
  }

  async function remove(id: number): Promise<void> {
    await api(`/${path}/${id}`, { method: 'DELETE' })
  }

  return { list, create, update, remove }
}
