<script setup lang="ts">
import {
  AlertTriangle,
  BarChart3,
  Boxes,
  ExternalLink,
  FileUp,
  ListTree,
  Package,
  Pencil,
  Plus,
  QrCode,
  Search,
  Trash2,
  Upload,
  Zap,
} from 'lucide-vue-next'

interface Option {
  id: number
  name: string
}

interface Subcategory {
  id: number
  name: string
  category_id: number
}

interface Variation {
  id: number
  color: string | null
  size: string | null
  ean_gtin: string | null
  product_code: string
  cost_price: string
  markup: string | null
  sale_price: string
  current_quantity: number
  min_quantity: number | null
  max_quantity: number | null
  wholesale_min_qty: number | null
  wholesale_price: string | null
}

interface Product {
  id: number
  name: string
  type: 'product' | 'service' | 'kit'
  type_label: string
  active: boolean
  unit_id: number
  location: string | null
  category_id: number
  subcategory_id: number | null
  brand_id: number | null
  supplier_id: number | null
  fiscal_fields: { ncm?: string; cfop?: string; cest?: string } | null
  category?: Option
  unit?: Option
  variations?: Variation[]
}

interface SkuRow {
  key: string
  product: Product
  variation: Variation | null
}

interface ProductSales {
  product_id: number
  product_name: string
  quantity_sold: number
  total: string
}

const productsApi = useResourceApi<Product>('products')
const unitsApi = useResourceApi<Option>('units')
const categoriesApi = useResourceApi<Option>('categories')
const subcategoriesApi = useResourceApi<Subcategory>('subcategories')
const brandsApi = useResourceApi<Option>('brands')
const suppliersApi = useResourceApi<{ id: number; corporate_name: string }>('suppliers')
const api = useApi()
const { parse, firstFieldError } = useApiError()
const { maskInput: maskCurrency, toNumber: currencyToNumber, format: formatCurrency } = useCurrencyMask()

// Custo + margem% -> preço de venda calculado ao vivo (o preço continua
// editável manualmente depois; o watch só recalcula quando custo/markup mudam).
function applyMarkup(costPrice: number, markupPercent: number): number {
  return costPrice * (1 + markupPercent / 100)
}
const auth = useAuthStore()

const products = ref<Product[]>([])
const salesLast30d = ref<ProductSales[]>([])
const units = ref<Option[]>([])
const categories = ref<Option[]>([])
const subcategories = ref<Subcategory[]>([])
const brands = ref<Option[]>([])
const suppliers = ref<{ id: number; corporate_name: string }[]>([])

const loading = ref(true)
const search = ref('')

const unitOptions = computed(() => units.value.map((u) => ({ value: u.id, label: u.name })))
const categoryOptions = computed(() => categories.value.map((c) => ({ value: c.id, label: c.name })))
const brandOptions = computed(() => brands.value.map((b) => ({ value: b.id, label: b.name })))
const supplierOptions = computed(() => suppliers.value.map((s) => ({ value: s.id, label: s.corporate_name })))

function isoDaysAgo(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return date.toISOString().slice(0, 10)
}

async function load() {
  loading.value = true
  const [productsResult, unitsResult, categoriesResult, subcategoriesResult, brandsResult, suppliersResult, salesResult] =
    await Promise.all([
      productsApi.list(),
      unitsApi.list(),
      categoriesApi.list(),
      subcategoriesApi.list(),
      brandsApi.list(),
      suppliersApi.list(),
      api<{ data: ProductSales[] }>(`/reports/sales-by-product?date_from=${isoDaysAgo(29)}`),
    ])
  products.value = productsResult
  units.value = unitsResult
  categories.value = categoriesResult
  subcategories.value = subcategoriesResult
  brands.value = brandsResult
  suppliers.value = suppliersResult
  salesLast30d.value = salesResult.data
  loading.value = false
}

const salesLast30dQty = computed(() => salesLast30d.value.reduce((sum, p) => sum + p.quantity_sold, 0))
const topSellingProducts = computed(() => salesLast30d.value.slice(0, 5))

const skuRows = computed<SkuRow[]>(() => {
  const rows: SkuRow[] = []
  for (const product of products.value) {
    if (!product.variations || product.variations.length === 0) {
      rows.push({ key: `product-${product.id}`, product, variation: null })
      continue
    }
    for (const variation of product.variations) {
      rows.push({ key: `variation-${variation.id}`, product, variation })
    }
  }
  return rows
})

const filteredRows = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return skuRows.value
  return skuRows.value.filter(
    (row) => row.product.name.toLowerCase().includes(query) || row.variation?.product_code.toLowerCase().includes(query),
  )
})

const totalStockQty = computed(() =>
  products.value.reduce((total, p) => total + (p.variations ?? []).reduce((sum, v) => sum + v.current_quantity, 0), 0),
)
const totalStockValue = computed(() =>
  products.value.reduce(
    (total, p) => total + (p.variations ?? []).reduce((sum, v) => sum + v.current_quantity * Number(v.sale_price), 0),
    0,
  ),
)
const lowStockCount = computed(() =>
  products.value.reduce(
    (total, p) =>
      total + (p.variations ?? []).filter((v) => v.min_quantity !== null && v.current_quantity <= v.min_quantity).length,
    0,
  ),
)
const noStockCount = computed(() =>
  products.value.reduce((total, p) => total + (p.variations ?? []).filter((v) => v.current_quantity <= 0).length, 0),
)
const excessStockCount = computed(() =>
  products.value.reduce(
    (total, p) =>
      total + (p.variations ?? []).filter((v) => v.max_quantity !== null && v.current_quantity > v.max_quantity).length,
    0,
  ),
)

function currencyBRL(value: number) {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function stockBadgeTone(variation: Variation): 'danger' | 'warning' | 'info' | 'success' {
  if (variation.current_quantity <= 0) return 'danger'
  if (variation.min_quantity !== null && variation.current_quantity <= variation.min_quantity) return 'warning'
  if (variation.max_quantity !== null && variation.current_quantity > variation.max_quantity) return 'info'
  return 'success'
}

// ---- Modal "Novo Produto" / "Editar Produto" — produto + primeira/atual variação juntos ----

const modalOpen = ref(false)
const modalSaving = ref(false)
const modalError = ref<unknown>(null)
const fiscalOpen = ref(false)

const editingProductId = ref<number | null>(null)
const editingVariationId = ref<number | null>(null)
// true quando o produto em edição ainda não tem nenhuma SKU — o campo de
// quantidade fica editável (equivale a criar a primeira variação); quando já
// existe SKU, a quantidade só muda via ajuste de estoque (regra do projeto).
const hasExistingVariation = computed(() => editingVariationId.value !== null)

const typeOptions = [
  { value: 'product', label: 'Produto' },
  { value: 'service', label: 'Serviço' },
  { value: 'kit', label: 'Kit' },
]

function emptyModalForm() {
  return {
    name: '',
    type: 'product',
    active: true,
    unit_id: '' as string | number,
    location: null as string | null,
    category_id: '' as string | number,
    subcategory_id: '' as string | number,
    brand_id: '' as string | number,
    supplier_id: '' as string | number,
    collection: '' as string | number,
    ncm: '',
    cfop: '',
    cest: '',
    ean_gtin: '',
    product_code: '',
    color: '',
    size: '',
    cost_price_masked: 'R$ 0,00',
    markup: '',
    sale_price_masked: 'R$ 0,00',
    quantity: 0,
    min_quantity: null as number | null,
    max_quantity: null as number | null,
    wholesale_min_qty: null as number | null,
    wholesale_price_masked: 'R$ 0,00',
  }
}

const modalForm = reactive(emptyModalForm())

watch(() => [modalForm.cost_price_masked, modalForm.markup], () => {
  if (modalForm.markup === '') return
  const markupPercent = Number(modalForm.markup)
  if (Number.isNaN(markupPercent)) return
  const cost = currencyToNumber(modalForm.cost_price_masked)
  modalForm.sale_price_masked = formatCurrency(Math.round(applyMarkup(cost, markupPercent) * 100))
})

// Aviso de possível duplicidade: compara o nome digitado com os produtos já
// carregados (client-side, sem endpoint novo) em ambas as direções — cobre
// tanto "Parafuso 4x30" digitado igual a um já cadastrado quanto uma versão
// mais curta/mais longa do mesmo nome. Não bloqueia o cadastro, só avisa.
function findSimilarProducts(name: string, excludeId: number | null) {
  const query = name.trim().toLowerCase()
  if (query.length < 3) return []
  return products.value
    .filter((p) => {
      if (p.id === excludeId) return false
      const productName = p.name.toLowerCase()
      return productName.includes(query) || query.includes(productName)
    })
    .slice(0, 5)
}

const modalSimilarProducts = computed(() => findSimilarProducts(modalForm.name, editingProductId.value))
const quickSimilarProducts = computed(() => findSimilarProducts(quickForm.name, null))

const subcategoryOptions = computed(() =>
  subcategories.value
    .filter((s) => String(s.category_id) === String(modalForm.category_id))
    .map((s) => ({ value: s.id, label: s.name })),
)

function openCreateModal() {
  editingProductId.value = null
  editingVariationId.value = null
  Object.assign(modalForm, emptyModalForm())
  modalError.value = null
  fiscalOpen.value = false
  modalOpen.value = true
}

function openEditModal(row: SkuRow) {
  editingProductId.value = row.product.id
  editingVariationId.value = row.variation?.id ?? null
  Object.assign(modalForm, emptyModalForm())
  modalForm.name = row.product.name
  modalForm.type = row.product.type
  modalForm.active = row.product.active
  modalForm.unit_id = row.product.unit_id
  modalForm.location = row.product.location
  modalForm.category_id = row.product.category_id
  modalForm.subcategory_id = row.product.subcategory_id ?? ''
  modalForm.brand_id = row.product.brand_id ?? ''
  modalForm.supplier_id = row.product.supplier_id ?? ''
  modalForm.ncm = row.product.fiscal_fields?.ncm ?? ''
  modalForm.cfop = row.product.fiscal_fields?.cfop ?? ''
  modalForm.cest = row.product.fiscal_fields?.cest ?? ''
  if (row.variation) {
    modalForm.ean_gtin = row.variation.ean_gtin ?? ''
    modalForm.product_code = row.variation.product_code
    modalForm.color = row.variation.color ?? ''
    modalForm.size = row.variation.size ?? ''
    modalForm.cost_price_masked = maskCurrency(String(Math.round(Number(row.variation.cost_price) * 100)))
    modalForm.markup = row.variation.markup ?? ''
    modalForm.sale_price_masked = maskCurrency(String(Math.round(Number(row.variation.sale_price) * 100)))
    modalForm.quantity = row.variation.current_quantity
    modalForm.min_quantity = row.variation.min_quantity
    modalForm.max_quantity = row.variation.max_quantity
    modalForm.wholesale_min_qty = row.variation.wholesale_min_qty
    modalForm.wholesale_price_masked = row.variation.wholesale_price
      ? maskCurrency(String(Math.round(Number(row.variation.wholesale_price) * 100)))
      : 'R$ 0,00'
  }
  modalError.value = null
  fiscalOpen.value = Boolean(modalForm.ncm || modalForm.cfop || modalForm.cest)
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
}

function handleModalCostInput(value: string) {
  modalForm.cost_price_masked = maskCurrency(value)
}
function handleModalSaleInput(value: string) {
  modalForm.sale_price_masked = maskCurrency(value)
}
function handleModalWholesaleInput(value: string) {
  modalForm.wholesale_price_masked = maskCurrency(value)
}

async function handleModalSubmit() {
  modalSaving.value = true
  modalError.value = null

  const hasFiscalFields = modalForm.ncm.trim() || modalForm.cfop.trim() || modalForm.cest.trim()
  const productPayload = {
    name: modalForm.name,
    type: modalForm.type,
    active: modalForm.active,
    unit_id: modalForm.unit_id,
    location: modalForm.location,
    category_id: modalForm.category_id,
    subcategory_id: modalForm.subcategory_id || null,
    brand_id: modalForm.brand_id || null,
    supplier_id: modalForm.supplier_id || null,
    fiscal_fields: hasFiscalFields
      ? { ncm: modalForm.ncm.trim() || null, cfop: modalForm.cfop.trim() || null, cest: modalForm.cest.trim() || null }
      : null,
  }

  try {
    const productId = editingProductId.value
      ? (await productsApi.update(editingProductId.value, productPayload)).id
      : (await productsApi.create(productPayload)).id

    const variationPayload = {
      product_code: modalForm.product_code,
      ean_gtin: modalForm.ean_gtin.trim() || null,
      color: modalForm.color.trim() || null,
      size: modalForm.size.trim() || null,
      cost_price: currencyToNumber(modalForm.cost_price_masked),
      markup: modalForm.markup === '' ? null : Number(modalForm.markup),
      sale_price: currencyToNumber(modalForm.sale_price_masked),
      min_quantity: modalForm.min_quantity,
      max_quantity: modalForm.max_quantity,
      wholesale_min_qty: modalForm.wholesale_min_qty,
      wholesale_price: modalForm.wholesale_min_qty !== null ? currencyToNumber(modalForm.wholesale_price_masked) : null,
    }

    if (editingVariationId.value) {
      await api(`/products/${productId}/variations/${editingVariationId.value}`, { method: 'PUT', body: variationPayload })
    } else {
      await api(`/products/${productId}/variations`, {
        method: 'POST',
        body: { ...variationPayload, initial_quantity: modalForm.quantity },
      })
    }

    closeModal()
    await load()
  } catch (err) {
    modalError.value = err
  } finally {
    modalSaving.value = false
  }
}

const { confirmDialog } = useConfirmDialog()

async function handleModalDelete() {
  if (!editingProductId.value) return
  const product = products.value.find((p) => p.id === editingProductId.value)
  if (!product) return
  const confirmed = await confirmDialog({
    title: 'Excluir produto',
    message: `Excluir o produto "${product.name}"?`,
    confirmLabel: 'Excluir',
    variant: 'danger',
  })
  if (!confirmed) return

  await productsApi.remove(editingProductId.value)
  closeModal()
  await load()
}

// ---- Painel "Cadastro Rápido" (toggle, cria produto + primeira SKU) ----

const quickOpen = ref(false)
const quickSaving = ref(false)
const quickError = ref<unknown>(null)

function emptyQuickForm() {
  return {
    name: '',
    unit_id: '' as string | number,
    category_id: '' as string | number,
    cost_price_masked: 'R$ 0,00',
    markup: '',
    sale_price_masked: 'R$ 0,00',
    initial_quantity: 0,
    ean_gtin: '' as string,
    wholesale_min_qty: null as number | null,
    wholesale_price_masked: 'R$ 0,00',
  }
}

const quickForm = reactive(emptyQuickForm())

watch(() => [quickForm.cost_price_masked, quickForm.markup], () => {
  if (quickForm.markup === '') return
  const markupPercent = Number(quickForm.markup)
  if (Number.isNaN(markupPercent)) return
  const cost = currencyToNumber(quickForm.cost_price_masked)
  quickForm.sale_price_masked = formatCurrency(Math.round(applyMarkup(cost, markupPercent) * 100))
})

function toggleQuickPanel() {
  quickOpen.value = !quickOpen.value
  if (quickOpen.value) {
    Object.assign(quickForm, emptyQuickForm())
    quickError.value = null
  }
}

function handleQuickCostInput(value: string) {
  quickForm.cost_price_masked = maskCurrency(value)
}
function handleQuickSaleInput(value: string) {
  quickForm.sale_price_masked = maskCurrency(value)
}
function handleQuickWholesaleInput(value: string) {
  quickForm.wholesale_price_masked = maskCurrency(value)
}

async function handleQuickSubmit() {
  quickSaving.value = true
  quickError.value = null

  try {
    const product = await productsApi.create({
      name: quickForm.name,
      type: 'product',
      unit_id: quickForm.unit_id,
      category_id: quickForm.category_id,
    })
    const code = quickForm.ean_gtin.trim() || `SKU-${Date.now().toString(36).toUpperCase()}`
    await api(`/products/${product.id}/variations`, {
      method: 'POST',
      body: {
        product_code: code,
        ean_gtin: quickForm.ean_gtin.trim() || null,
        cost_price: currencyToNumber(quickForm.cost_price_masked),
        markup: quickForm.markup === '' ? null : Number(quickForm.markup),
        sale_price: currencyToNumber(quickForm.sale_price_masked),
        initial_quantity: quickForm.initial_quantity,
        wholesale_min_qty: quickForm.wholesale_min_qty,
        wholesale_price: quickForm.wholesale_min_qty !== null ? currencyToNumber(quickForm.wholesale_price_masked) : null,
      },
    })
    quickOpen.value = false
    await load()
  } catch (err) {
    quickError.value = err
  } finally {
    quickSaving.value = false
  }
}

async function handleDelete(product: Product) {
  const confirmed = await confirmDialog({
    title: 'Excluir produto',
    message: `Excluir o produto "${product.name}"?`,
    confirmLabel: 'Excluir',
    variant: 'danger',
  })
  if (!confirmed) return

  await productsApi.remove(product.id)
  await load()
}

// ---- Modal "Variações" (SKUs de um produto) ----

const skuModalOpen = ref(false)
const skuModalLoading = ref(false)
const skuModalProduct = ref<Product | null>(null)
const skuSaving = ref(false)
const skuError = ref<unknown>(null)
const skuEditingId = ref<number | null>(null)

function emptySkuForm() {
  return {
    color: null as string | null,
    size: null as string | null,
    ean_gtin: null as string | null,
    product_code: '',
    cost_price_masked: 'R$ 0,00',
    markup: '',
    sale_price_masked: 'R$ 0,00',
    initial_quantity: 0,
    min_quantity: null as number | null,
    max_quantity: null as number | null,
    wholesale_min_qty: null as number | null,
    wholesale_price_masked: 'R$ 0,00',
  }
}

const skuForm = reactive(emptySkuForm())

watch(() => [skuForm.cost_price_masked, skuForm.markup], () => {
  if (skuForm.markup === '') return
  const markupPercent = Number(skuForm.markup)
  if (Number.isNaN(markupPercent)) return
  const cost = currencyToNumber(skuForm.cost_price_masked)
  skuForm.sale_price_masked = formatCurrency(Math.round(applyMarkup(cost, markupPercent) * 100))
})

async function loadSkuModalProduct(productId: number) {
  skuModalLoading.value = true
  const res = await api<{ data: Product }>(`/products/${productId}`)
  skuModalProduct.value = res.data
  skuModalLoading.value = false
}

async function openVariationsModal(productId: number) {
  skuEditingId.value = null
  Object.assign(skuForm, emptySkuForm())
  skuError.value = null
  skuModalOpen.value = true
  await loadSkuModalProduct(productId)
}

function closeVariationsModal() {
  skuModalOpen.value = false
  skuModalProduct.value = null
}

function startEditSku(variation: Variation) {
  skuEditingId.value = variation.id
  skuForm.color = variation.color
  skuForm.size = variation.size
  skuForm.ean_gtin = variation.ean_gtin
  skuForm.product_code = variation.product_code
  skuForm.cost_price_masked = maskCurrency(String(Math.round(Number(variation.cost_price) * 100)))
  skuForm.markup = variation.markup ?? ''
  skuForm.sale_price_masked = maskCurrency(String(Math.round(Number(variation.sale_price) * 100)))
  skuForm.min_quantity = variation.min_quantity
  skuForm.max_quantity = variation.max_quantity
  skuForm.wholesale_min_qty = variation.wholesale_min_qty
  skuForm.wholesale_price_masked = variation.wholesale_price
    ? maskCurrency(String(Math.round(Number(variation.wholesale_price) * 100)))
    : 'R$ 0,00'
}

function resetSkuForm() {
  skuEditingId.value = null
  Object.assign(skuForm, emptySkuForm())
}

function handleSkuCostInput(value: string) {
  skuForm.cost_price_masked = maskCurrency(value)
}
function handleSkuSaleInput(value: string) {
  skuForm.sale_price_masked = maskCurrency(value)
}
function handleSkuWholesaleInput(value: string) {
  skuForm.wholesale_price_masked = maskCurrency(value)
}

async function handleSkuSubmit() {
  if (!skuModalProduct.value) return
  skuSaving.value = true
  skuError.value = null

  const payload = {
    color: skuForm.color,
    size: skuForm.size,
    ean_gtin: skuForm.ean_gtin,
    product_code: skuForm.product_code,
    cost_price: currencyToNumber(skuForm.cost_price_masked),
    markup: skuForm.markup === '' ? null : Number(skuForm.markup),
    sale_price: currencyToNumber(skuForm.sale_price_masked),
    min_quantity: skuForm.min_quantity,
    max_quantity: skuForm.max_quantity,
    wholesale_min_qty: skuForm.wholesale_min_qty,
    wholesale_price: skuForm.wholesale_min_qty !== null ? currencyToNumber(skuForm.wholesale_price_masked) : null,
    ...(skuEditingId.value ? {} : { initial_quantity: skuForm.initial_quantity }),
  }

  try {
    if (skuEditingId.value) {
      await api(`/products/${skuModalProduct.value.id}/variations/${skuEditingId.value}`, { method: 'PUT', body: payload })
    } else {
      await api(`/products/${skuModalProduct.value.id}/variations`, { method: 'POST', body: payload })
    }
    resetSkuForm()
    await loadSkuModalProduct(skuModalProduct.value.id)
    await load()
  } catch (err) {
    skuError.value = err
  } finally {
    skuSaving.value = false
  }
}

async function handleSkuDelete(variation: Variation) {
  if (!skuModalProduct.value) return
  const confirmed = await confirmDialog({
    title: 'Excluir variação',
    message: `Excluir a variação "${variation.product_code}"?`,
    confirmLabel: 'Excluir',
    variant: 'danger',
  })
  if (!confirmed) return

  await api(`/products/${skuModalProduct.value.id}/variations/${variation.id}`, { method: 'DELETE' })
  await loadSkuModalProduct(skuModalProduct.value.id)
  await load()
}

// ---- Modal "Importar produtos" — UI presente, processamento ainda não existe ----
const importOpen = ref(false)

await load()
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Produtos</h1>
      <p class="text-sm text-txt-secondary">Gerencie cadastros, estoque e preços.</p>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <StatCard label="Produtos" :value="products.length" subtext="cadastros encontrados" :icon="Package" tone="sky" />
      <StatCard label="Estoque na página" :value="totalStockQty" :subtext="currencyBRL(totalStockValue)" :icon="ListTree" tone="emerald" />
      <StatCard
        label="Alertas"
        :value="lowStockCount"
        :subtext="`${noStockCount} sem estoque · ${excessStockCount} em excesso`"
        :icon="AlertTriangle"
        :tone="lowStockCount > 0 ? 'warning' : 'emerald'"
      />
      <StatCard label="Vendas 30 dias" :value="salesLast30dQty" subtext="Unidades vendidas" :icon="BarChart3" tone="sky" />
    </div>

    <div class="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <div class="flex items-start justify-between">
          <div>
            <span class="text-[10.5px] font-bold tracking-wide text-amber-700 uppercase">Giro de estoque</span>
            <p class="font-display text-sm font-bold text-txt-primary">Produtos que mais saem</p>
          </div>
          <span class="rounded-full border border-border bg-surface px-3 py-1.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
            Últimos 30 dias
          </span>
        </div>
        <div class="mt-4">
          <BarSparkline
            :data="topSellingProducts.map((p) => ({ label: p.product_name, value: p.quantity_sold }))"
            tone="emerald"
          />
        </div>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <span class="text-[10.5px] font-bold tracking-wide text-amber-700 uppercase">Ranking</span>
        <p class="font-display text-sm font-bold text-txt-primary">Mais vendidos</p>
        <div v-if="topSellingProducts.length" class="mt-3 divide-y divide-border">
          <div
            v-for="(product, index) in topSellingProducts.slice(0, 3)"
            :key="product.product_id"
            class="flex items-center justify-between py-2 text-sm"
          >
            <span class="truncate text-txt-secondary">{{ index + 1 }}. {{ product.product_name }}</span>
            <span class="shrink-0 font-display font-bold text-txt-primary">{{ product.quantity_sold }}</span>
          </div>
        </div>
        <div v-else class="mt-3 flex h-24 items-center justify-center rounded-xl border border-dashed border-border text-center text-xs text-txt-muted">
          Sem vendas finalizadas no período.
        </div>
      </div>
    </div>

    <div v-if="auth.isAdmin" class="flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card">
      <BaseButton :block="false" @click="openCreateModal">
        <Plus :size="15" />
        Novo Produto
      </BaseButton>
      <BaseButton :variant="quickOpen ? 'primary' : 'ghost'" :block="false" @click="toggleQuickPanel">
        <Zap :size="15" />
        Cadastro Rápido
      </BaseButton>
      <BaseButton variant="ghost" :block="false" @click="importOpen = true">
        <Upload :size="15" />
        IMPORTAR
      </BaseButton>
      <div class="flex-1" />
      <label class="flex w-full max-w-xs items-center gap-2 rounded-full border border-border bg-surface px-3.5 py-2 text-txt-muted">
        <input v-model="search" type="text" placeholder="Filtrar produtos..." class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none">
        <Search :size="15" />
      </label>
    </div>

    <div v-if="quickOpen" class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
      <form class="space-y-4" @submit.prevent="handleQuickSubmit">
        <div>
          <StatusBadge label="Cadastro rápido" tone="success" />
          <p class="mt-2 font-display text-lg font-bold text-txt-primary">Novo produto</p>
          <p class="text-sm text-txt-secondary">Cadastre um item de forma resumida, mantendo o padrão visual do projeto.</p>
        </div>

        <BaseInput v-model="quickForm.name" label="Nome" :error="firstFieldError(quickError, 'name')" />
        <div v-if="quickSimilarProducts.length > 0" class="rounded-xl border border-amber-200 bg-amber-50 p-3">
          <p class="flex items-center gap-1.5 text-xs font-bold text-amber-800">
            <AlertTriangle :size="13" />
            Produto parecido já cadastrado
          </p>
          <ul class="mt-1 space-y-0.5 pl-5 text-xs text-amber-800">
            <li v-for="similar in quickSimilarProducts" :key="similar.id" class="list-disc">{{ similar.name }}</li>
          </ul>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <BaseSelect v-model="quickForm.category_id" label="Categoria" :options="categoryOptions" :error="firstFieldError(quickError, 'category_id')" />
          <BaseSelect v-model="quickForm.unit_id" label="Unidade" :options="unitOptions" :error="firstFieldError(quickError, 'unit_id')" />
        </div>

        <div class="grid grid-cols-3 gap-4">
          <BaseInput
            :model-value="quickForm.cost_price_masked"
            label="Custo"
            :error="firstFieldError(quickError, 'cost_price')"
            @update:model-value="handleQuickCostInput"
          />
          <BaseInput v-model="quickForm.markup" label="Lucro (%)" :error="firstFieldError(quickError, 'markup')" />
          <BaseInput
            :model-value="quickForm.sale_price_masked"
            label="Valor de venda"
            :error="firstFieldError(quickError, 'sale_price')"
            @update:model-value="handleQuickSaleInput"
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <BaseInput
            v-model.number="quickForm.initial_quantity"
            type="number"
            label="Quantidade inicial"
            :error="firstFieldError(quickError, 'initial_quantity')"
          />
          <BaseInput v-model="quickForm.ean_gtin" label="Código de barras (EAN/GTIN)" :error="firstFieldError(quickError, 'ean_gtin')" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <BaseInput
            v-model.number="quickForm.wholesale_min_qty"
            type="number"
            label="Qtd. p/ Preço Atacado"
            :error="firstFieldError(quickError, 'wholesale_min_qty')"
          />
          <BaseInput
            :model-value="quickForm.wholesale_price_masked"
            label="Preço Atacado"
            :error="firstFieldError(quickError, 'wholesale_price')"
            @update:model-value="handleQuickWholesaleInput"
          />
        </div>

        <p v-if="quickError" class="text-sm text-rose-600">{{ parse(quickError).message }}</p>

        <div class="flex justify-end gap-3 border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="quickOpen = false">Cancelar</BaseButton>
          <BaseButton type="submit" :loading="quickSaving" :block="false">Salvar rápido</BaseButton>
        </div>
      </form>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
      <div class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
        <span>Produto</span>
        <span>Código</span>
        <span>Preço</span>
        <span>Estoque</span>
        <span class="text-right">Ações</span>
      </div>

      <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="filteredRows.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">
        Nenhum produto cadastrado ainda.
      </div>
      <div
        v-for="row in filteredRows"
        v-else
        :key="row.key"
        class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
      >
        <span class="flex items-center gap-2 text-sm font-medium text-txt-primary">
          {{ row.product.name }}
          <StatusBadge v-if="!row.product.active" label="Inativo" tone="danger" />
        </span>
        <span class="text-sm text-txt-secondary">{{ row.variation?.product_code ?? '—' }}</span>
        <span class="text-sm text-txt-secondary">{{ row.variation ? currencyBRL(Number(row.variation.sale_price)) : '—' }}</span>
        <span v-if="row.variation">
          <StatusBadge :label="String(row.variation.current_quantity)" :tone="stockBadgeTone(row.variation)" />
        </span>
        <span v-else class="text-sm text-txt-muted">—</span>
        <div class="flex justify-end gap-1">
          <IconButton :icon="ListTree" label="Ver variações" @click="openVariationsModal(row.product.id)" />
          <IconButton v-if="auth.isAdmin" :icon="Pencil" label="Editar Produto" @click="openEditModal(row)" />
          <IconButton v-if="auth.isAdmin" :icon="Trash2" label="Excluir" tone="danger" @click="handleDelete(row.product)" />
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3.5">
        <div class="flex flex-wrap items-center gap-4">
          <span class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-txt-secondary uppercase">
            <span class="h-3 w-3 rounded-sm bg-amber-300" />
            Estoque mínimo atingido
          </span>
          <span class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-txt-secondary uppercase">
            <span class="h-3 w-3 rounded-sm bg-rose-300" />
            Sem estoque
          </span>
          <span class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-txt-secondary uppercase">
            <span class="h-3 w-3 rounded-sm bg-sky-300" />
            Estoque acima do máximo
          </span>
        </div>
        <span class="text-xs text-txt-secondary">
          Exibindo <strong class="text-txt-primary">{{ filteredRows.length }}</strong> de
          <strong class="text-txt-primary">{{ skuRows.length }}</strong>
        </span>
      </div>
    </div>

    <BaseModal
      :open="modalOpen"
      size="xl"
      eyebrow="Cadastro"
      title="Produto"
      subtitle="Preencha os dados do produto, configure a variação e salve para concluir."
      @close="closeModal"
    >
      <form class="space-y-5" @submit.prevent="handleModalSubmit">
        <div class="rounded-2xl border border-border bg-surface-raised p-5">
          <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="font-display text-sm font-bold text-txt-primary">Dados do produto</p>
              <p class="text-xs text-txt-secondary">Preencha as informações principais para cadastrar ou editar o produto.</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
              <BaseSwitch v-model="modalForm.active" label="Produto ativo" />
              <BaseButton type="button" variant="ghost" :block="false" @click="navigateTo('/settings/catalog')">
                <ExternalLink :size="13" />
                Gerenciar catálogo
              </BaseButton>
              <StatusBadge label="Cadastro" tone="success" />
            </div>
          </div>

          <div class="space-y-4">
            <div>
              <BaseInput v-model="modalForm.name" label="Nome" :error="firstFieldError(modalError, 'name')" />
              <p class="mt-1 text-[11px] text-txt-muted">Use um nome claro para facilitar busca, venda e reposição.</p>
              <div v-if="modalSimilarProducts.length > 0" class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-3">
                <p class="flex items-center gap-1.5 text-xs font-bold text-amber-800">
                  <AlertTriangle :size="13" />
                  Produto parecido já cadastrado
                </p>
                <ul class="mt-1 space-y-0.5 pl-5 text-xs text-amber-800">
                  <li v-for="similar in modalSimilarProducts" :key="similar.id" class="list-disc">{{ similar.name }}</li>
                </ul>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <BaseSelect v-model="modalForm.supplier_id" label="Fornecedor" :options="supplierOptions" placeholder="Nenhum" :error="firstFieldError(modalError, 'supplier_id')" />
              <BaseSelect v-model="modalForm.type" label="Tipo do produto" :options="typeOptions" :error="firstFieldError(modalError, 'type')" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <BaseSelect v-model="modalForm.unit_id" label="Unidade" :options="unitOptions" :error="firstFieldError(modalError, 'unit_id')" />
              <BaseInput v-model="modalForm.location" label="Localização no estoque" :error="firstFieldError(modalError, 'location')" />
            </div>

            <div class="rounded-xl border border-dashed border-border p-3.5">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p class="text-xs font-bold text-txt-primary">Campos fiscais</p>
                  <p class="text-[11px] text-txt-muted">Preencha apenas se o produto for usado na emissão de Nota Fiscal.</p>
                </div>
                <BaseButton type="button" variant="ghost" :block="false" @click="fiscalOpen = !fiscalOpen">
                  {{ fiscalOpen ? 'Ocultar campos fiscais' : 'Mostrar campos fiscais' }}
                </BaseButton>
              </div>
              <div v-if="fiscalOpen" class="mt-3.5 grid grid-cols-3 gap-3">
                <BaseInput v-model="modalForm.ncm" label="NCM" />
                <BaseInput v-model="modalForm.cfop" label="CFOP" />
                <BaseInput v-model="modalForm.cest" label="CEST" />
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface-raised p-5">
          <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="font-display text-sm font-bold text-txt-primary">Classificação</p>
              <p class="text-xs text-txt-secondary">Organize o produto por coleção, marca, categoria e subcategoria.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <BaseButton type="button" variant="ghost" :block="false" @click="navigateTo('/settings/catalog')">
                <ExternalLink :size="13" />
                Gerenciar catálogo
              </BaseButton>
              <StatusBadge label="Organização" tone="success" />
            </div>
          </div>

          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <BaseSelect v-model="modalForm.collection" label="Coleção" :options="[]" placeholder="Em breve" disabled />
              <BaseSelect v-model="modalForm.brand_id" label="Marca" :options="brandOptions" placeholder="Nenhuma" :error="firstFieldError(modalError, 'brand_id')" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <BaseSelect v-model="modalForm.category_id" label="Categoria" :options="categoryOptions" :error="firstFieldError(modalError, 'category_id')" />
              <BaseSelect
                v-model="modalForm.subcategory_id"
                label="Subcategoria"
                :options="subcategoryOptions"
                placeholder="Nenhuma"
                :error="firstFieldError(modalError, 'subcategory_id')"
              />
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface-raised p-5">
          <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
              <StatusBadge label="Variações" tone="success" />
              <p class="mt-2 font-display text-sm font-bold text-txt-primary">Preço, estoque e identificação</p>
              <p class="text-xs text-txt-secondary">
                {{ hasExistingVariation ? 'Editando a SKU selecionada — quantidade só muda por ajuste de estoque.' : 'Cadastre a variação com seus códigos, valores e saldo.' }}
              </p>
            </div>
            <BaseButton type="button" variant="ghost" :block="false" disabled title="Em breve">Gerenciar cores e tamanhos</BaseButton>
          </div>

          <div class="rounded-xl border border-border p-4">
            <p class="text-sm font-bold text-txt-primary">Variação 1</p>
            <p class="mb-4 text-xs text-txt-secondary">Identificação, preço e estoque desta variação.</p>

            <p class="mb-2 text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Identificação</p>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <div class="flex items-end gap-1.5">
                <div class="flex-1">
                  <BaseInput v-model="modalForm.ean_gtin" label="Código de barras (EAN/GTIN)" :error="firstFieldError(modalError, 'ean_gtin')" />
                </div>
                <button
                  type="button"
                  disabled
                  title="Escanear código de barras (em breve)"
                  class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-xl border border-border text-txt-muted disabled:cursor-not-allowed"
                >
                  <QrCode :size="16" />
                </button>
              </div>
              <BaseInput v-model="modalForm.product_code" label="Código do produto" :error="firstFieldError(modalError, 'product_code')" />
              <BaseInput v-model="modalForm.color" label="Cor" :error="firstFieldError(modalError, 'color')" />
              <BaseInput v-model="modalForm.size" label="Tamanho" :error="firstFieldError(modalError, 'size')" />
            </div>

            <p class="mt-4 mb-2 text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Preço e estoque</p>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
              <BaseInput
                :model-value="modalForm.cost_price_masked"
                label="Custo"
                :error="firstFieldError(modalError, 'cost_price')"
                @update:model-value="handleModalCostInput"
              />
              <BaseInput v-model="modalForm.markup" label="Lucro (%)" :error="firstFieldError(modalError, 'markup')" />
              <BaseInput
                :model-value="modalForm.sale_price_masked"
                label="Valor de venda"
                :error="firstFieldError(modalError, 'sale_price')"
                @update:model-value="handleModalSaleInput"
              />
              <BaseInput
                v-if="hasExistingVariation"
                :model-value="modalForm.quantity"
                label="Quantidade atual"
                disabled
              />
              <BaseInput
                v-else
                v-model.number="modalForm.quantity"
                type="number"
                label="Quantidade inicial"
                :error="firstFieldError(modalError, 'initial_quantity')"
              />
              <BaseInput v-model.number="modalForm.max_quantity" type="number" label="Qtde máxima" :error="firstFieldError(modalError, 'max_quantity')" />
              <BaseInput v-model.number="modalForm.min_quantity" type="number" label="Qtde mínima" :error="firstFieldError(modalError, 'min_quantity')" />
            </div>

            <p class="mt-4 mb-2 text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Preço de atacado (opcional)</p>
            <div class="grid grid-cols-2 gap-4">
              <BaseInput
                v-model.number="modalForm.wholesale_min_qty"
                type="number"
                label="Qtd. p/ Preço Atacado"
                :error="firstFieldError(modalError, 'wholesale_min_qty')"
              />
              <BaseInput
                :model-value="modalForm.wholesale_price_masked"
                label="Preço Atacado"
                :error="firstFieldError(modalError, 'wholesale_price')"
                @update:model-value="handleModalWholesaleInput"
              />
            </div>
          </div>
        </div>

        <p v-if="modalError" class="text-sm text-rose-600">{{ parse(modalError).message }}</p>

        <div class="flex items-center justify-between gap-3 border-t border-border pt-4">
          <BaseButton v-if="editingProductId" type="button" variant="danger" :block="false" @click="handleModalDelete">
            <Trash2 :size="15" />
            Excluir produto
          </BaseButton>
          <div v-else />
          <div class="flex gap-3">
            <BaseButton type="button" variant="ghost" :block="false" @click="closeModal">Cancelar</BaseButton>
            <BaseButton type="submit" :loading="modalSaving" :block="false">Salvar produto</BaseButton>
          </div>
        </div>
      </form>
    </BaseModal>

    <BaseModal :open="importOpen" title="Importar produtos" subtitle="Use esta importação para produtos novos." @close="importOpen = false">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-border p-8 text-center opacity-60">
          <FileUp :size="28" class="text-txt-muted" />
          <p class="text-sm font-bold text-txt-primary">Selecionar XML ou planilha</p>
          <p class="text-xs text-txt-muted">Arquivos aceitos: XML de NF-e, XLSX ou XLS.</p>
          <StatusBadge label="Em breve" tone="warning" />
        </div>
        <div class="rounded-2xl border border-border p-5">
          <div class="flex items-center gap-2 text-amber-700">
            <AlertTriangle :size="16" />
            <span class="text-sm font-bold">Antes de importar</span>
          </div>
          <p class="mt-2 text-xs text-txt-secondary">
            Essa importação em massa ainda não foi implementada — por enquanto, cadastre pelo "Novo Produto" ou "Cadastro Rápido".
          </p>
          <BaseButton variant="ghost" :block="false" disabled class="mt-3">Baixar modelo XLSX</BaseButton>
        </div>
      </div>

      <div class="mt-5 flex justify-end gap-3 border-t border-border pt-4">
        <BaseButton type="button" variant="ghost" :block="false" @click="importOpen = false">Fechar</BaseButton>
        <BaseButton type="button" disabled :block="false">Importar</BaseButton>
      </div>
    </BaseModal>

    <BaseModal
      :open="skuModalOpen"
      size="lg"
      :title="`Variações de ${skuModalProduct?.name ?? ''}`"
      subtitle="Cada variação é um SKU com preço e estoque próprios."
      @close="closeVariationsModal"
    >
      <div class="space-y-5">
        <StatCard label="Total de variações" :value="skuModalProduct?.variations?.length ?? 0" :icon="Boxes" tone="emerald" class="max-w-xs" />

        <div v-if="auth.isAdmin" class="rounded-2xl border border-border bg-surface-raised p-5">
          <form class="space-y-4" @submit.prevent="handleSkuSubmit">
            <p class="text-xs font-semibold tracking-wide text-txt-muted uppercase">
              {{ skuEditingId ? 'Editar variação' : 'Nova variação (SKU)' }}
            </p>
            <div class="grid grid-cols-3 gap-4">
              <BaseInput v-model="skuForm.color" label="Cor" :error="firstFieldError(skuError, 'color')" />
              <BaseInput v-model="skuForm.size" label="Tamanho" :error="firstFieldError(skuError, 'size')" />
              <BaseInput v-model="skuForm.ean_gtin" label="EAN/GTIN" :error="firstFieldError(skuError, 'ean_gtin')" />
            </div>
            <BaseInput v-model="skuForm.product_code" label="Código do produto" :error="firstFieldError(skuError, 'product_code')" />
            <div class="grid grid-cols-3 gap-4">
              <BaseInput
                :model-value="skuForm.cost_price_masked"
                label="Preço de custo"
                :error="firstFieldError(skuError, 'cost_price')"
                @update:model-value="handleSkuCostInput"
              />
              <BaseInput v-model="skuForm.markup" label="Lucro (%)" :error="firstFieldError(skuError, 'markup')" />
              <BaseInput
                :model-value="skuForm.sale_price_masked"
                label="Preço de venda"
                :error="firstFieldError(skuError, 'sale_price')"
                @update:model-value="handleSkuSaleInput"
              />
            </div>
            <div class="grid grid-cols-3 gap-4">
              <BaseInput
                v-if="!skuEditingId"
                v-model.number="skuForm.initial_quantity"
                type="number"
                label="Quantidade inicial"
                :error="firstFieldError(skuError, 'initial_quantity')"
              />
              <BaseInput v-model.number="skuForm.min_quantity" type="number" label="Estoque mínimo" :error="firstFieldError(skuError, 'min_quantity')" />
              <BaseInput v-model.number="skuForm.max_quantity" type="number" label="Estoque máximo" :error="firstFieldError(skuError, 'max_quantity')" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <BaseInput
                v-model.number="skuForm.wholesale_min_qty"
                type="number"
                label="Qtd. p/ Preço Atacado"
                :error="firstFieldError(skuError, 'wholesale_min_qty')"
              />
              <BaseInput
                :model-value="skuForm.wholesale_price_masked"
                label="Preço Atacado"
                :error="firstFieldError(skuError, 'wholesale_price')"
                @update:model-value="handleSkuWholesaleInput"
              />
            </div>

            <p v-if="skuError" class="text-sm text-rose-600">{{ parse(skuError).message }}</p>

            <div class="flex gap-3">
              <BaseButton type="submit" :loading="skuSaving" :block="false">
                <Plus :size="16" />
                {{ skuEditingId ? 'Salvar' : 'Adicionar' }}
              </BaseButton>
              <BaseButton v-if="skuEditingId" type="button" variant="ghost" :block="false" @click="resetSkuForm">Cancelar</BaseButton>
            </div>
          </form>
        </div>

        <BaseTable
          :items="skuModalProduct?.variations ?? []"
          :loading="skuModalLoading"
          :columns="[
            { key: 'product_code', label: 'Código' },
            { key: 'sale_price', label: 'Preço de venda' },
            { key: 'current_quantity', label: 'Estoque' },
          ]"
        >
          <template #cell-current_quantity="{ item }">
            <StatusBadge :label="String(item.current_quantity)" :tone="stockBadgeTone(item)" />
          </template>
          <template v-if="auth.isAdmin" #actions="{ item }">
            <IconButton :icon="Pencil" label="Editar" @click="startEditSku(item)" />
            <IconButton :icon="Trash2" label="Excluir" tone="danger" @click="handleSkuDelete(item)" />
          </template>
        </BaseTable>
      </div>
    </BaseModal>
  </div>
</template>
