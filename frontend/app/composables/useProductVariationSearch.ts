export interface ProductVariation {
  id: number
  color: string | null
  size: string | null
  ean_gtin: string | null
  product_code: string
  sale_price: string
  current_quantity: number
  max_quantity: number | null
  markup: string | null
  wholesale_min_qty: number | null
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

  async function search(query: string, limit = 20): Promise<ProductVariationRow[]> {
    const trimmed = query.trim()
    if (!trimmed) return []
    searching.value = true
    try {
      const res = await api<{ data: SearchApiRow[] }>(
        `/product-variations/search?q=${encodeURIComponent(trimmed)}&limit=${limit}`,
      )
      return res.data.map(toRow)
    } finally {
      searching.value = false
    }
  }

  return { searching, findExact, search }
}
