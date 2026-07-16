export interface ProductVariation {
  id: number
  color: string | null
  size: string | null
  ean_gtin: string | null
  product_code: string
  sale_price: string
  current_quantity: number
  wholesale_min_qty: number | null
  wholesale_price: string | null
}

export interface ProductWithVariations {
  id: number
  name: string
  active: boolean
  variations?: ProductVariation[]
}

export interface ProductVariationRow {
  key: string
  productName: string
  variationLabel: string | null
  variation: ProductVariation
}

/**
 * Não existe endpoint de busca de produto dedicado no backend — PDV e a tela de
 * Etiquetas reaproveitam GET /products (variações já vêm aninhadas) e filtram
 * no cliente. Centraliza aqui o achatamento produto→variação e a busca por
 * nome/código/EAN usados nos dois lugares.
 */
export function useProductVariationSearch() {
  const products = ref<ProductWithVariations[]>([])

  async function loadProducts() {
    const api = useApi()
    const response = await api<{ data: ProductWithVariations[] }>('/products')
    products.value = response.data
  }

  const rows = computed<ProductVariationRow[]>(() => {
    const result: ProductVariationRow[] = []
    for (const product of products.value) {
      if (!product.active) continue
      for (const variation of product.variations ?? []) {
        const label = [variation.color, variation.size].filter(Boolean).join(' / ') || null
        result.push({ key: `${variation.id}`, productName: product.name, variationLabel: label, variation })
      }
    }
    return result
  })

  const exactMatchIndex = computed(() => {
    const map = new Map<string, ProductVariationRow>()
    for (const row of rows.value) {
      if (row.variation.ean_gtin) map.set(row.variation.ean_gtin.toLowerCase(), row)
      map.set(row.variation.product_code.toLowerCase(), row)
    }
    return map
  })

  function findExact(query: string): ProductVariationRow | null {
    return exactMatchIndex.value.get(query.trim().toLowerCase()) ?? null
  }

  function findFuzzy(query: string): ProductVariationRow | null {
    const q = query.trim().toLowerCase()
    if (!q) return null
    return rows.value.find(
      (row) => row.productName.toLowerCase().includes(q) || row.variation.product_code.toLowerCase().includes(q),
    ) ?? null
  }

  function filter(query: string): ProductVariationRow[] {
    const q = query.trim().toLowerCase()
    if (!q) return rows.value
    return rows.value.filter(
      (row) => row.productName.toLowerCase().includes(q) || row.variation.product_code.toLowerCase().includes(q),
    )
  }

  return { products, loadProducts, rows, exactMatchIndex, findExact, findFuzzy, filter }
}
