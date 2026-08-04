export interface ProductVariation {
  id: number
  color: string | null
  size: string | null
  ean_gtin: string | null
  code: string
  sale_price: string
  current_quantity: string
  max_quantity: string | null
  markup: string | null
  wholesale_min_qty: string | null
  wholesale_price: string | null
}

export interface ProductVariationRow {
  key: string
  productName: string
  variationLabel: string | null
  variation: ProductVariation
}

interface SearchApiRow extends ProductVariation {
  product_name: string
}

function toRow(item: SearchApiRow): ProductVariationRow {
  const label = [item.color, item.size].filter(Boolean).join(' / ') || null
  const { product_name, ...variation } = item
  return { key: `${item.id}`, productName: product_name, variationLabel: label, variation }
}

/**
 * Busca de produto no PDV, no seletor F2 e nas Etiquetas - roda no banco
 * (GET /product-variations/lookup|search) em vez de carregar o catálogo
 * inteiro no navegador. Trocado depois de um achado real: com os 13 mil
 * produtos importados do sistema legado, carregar tudo de uma vez estourava
 * a memória do PHP e mandava ~14MB de JSON pro navegador a cada tela (ver
 * docs/11-migracao-sistema-legado.md).
 */
export function useProductVariationSearch() {
  const api = useApi()
  const searching = ref(false)
  // Protege contra condição de corrida: se o usuário digita rápido (ex.
  // "10*paraf"), o debounce de cada tela dispara uma busca por termo
  // intermediário ("10", "10*", "10*p"...) sempre que há uma pausa >200ms
  // entre teclas - e nada garante que essas respostas voltam na ordem em
  // que foram pedidas. Sem essa proteção, uma resposta velha (de um termo
  // que não bate com nada) podia chegar depois da resposta certa e
  // sobrescrever o resultado correto com uma lista vazia - o produto
  // "sumia" até o usuário buscar de novo. Achado real do cliente
  // (2026-08-04), mais frequente com o prefixo "10*" por causa da pausa
  // natural entre digitar o prefixo e o termo de busca.
  let latestRequestId = 0

  async function findExact(code: string): Promise<ProductVariationRow | null> {
    const trimmed = code.trim()
    if (!trimmed) return null
    try {
      const res = await api<{ data: SearchApiRow }>(`/product-variations/lookup?code=${encodeURIComponent(trimmed)}`)
      return toRow(res.data)
    } catch {
      return null
    }
  }

  /**
   * Retorna `null` quando essa busca foi superada por uma mais recente -
   * nesse caso o chamador deve ignorar o retorno (não sobrescrever a lista
   * já exibida), não tratar como "sem resultado".
   */
  async function search(query: string, limit = 20): Promise<ProductVariationRow[] | null> {
    const trimmed = query.trim()
    if (!trimmed) return []
    const requestId = ++latestRequestId
    searching.value = true
    try {
      const res = await api<{ data: SearchApiRow[] }>(
        `/product-variations/search?q=${encodeURIComponent(trimmed)}&limit=${limit}`,
      )
      if (requestId !== latestRequestId) return null
      return res.data.map(toRow)
    } catch {
      return requestId === latestRequestId ? [] : null
    } finally {
      if (requestId === latestRequestId) searching.value = false
    }
  }

  return { searching, findExact, search }
}
