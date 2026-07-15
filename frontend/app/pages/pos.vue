<script setup lang="ts">
import {
  Boxes,
  ChevronDown,
  LogOut,
  Minus,
  Plus,
  Search,
  ShieldCheck,
  Trash2,
  User as UserIcon,
  UserPlus,
  X,
} from 'lucide-vue-next'
import { lineTotalCents } from '~/utils/cartMath'
import type { CartItem } from '~/stores/cart'

definePageMeta({ layout: 'pos' })
useHead({ title: 'PDV — JP Parafusos' })

interface Variation {
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

interface Product {
  id: number
  name: string
  active: boolean
  variations?: Variation[]
}

interface SkuRow {
  key: string
  productName: string
  variationLabel: string | null
  variation: Variation
}

interface Customer {
  id: number
  name: string
  mobile_phone: string
  phone: string | null
}

interface PaymentMethod {
  id: number
  name: string
  active_on_pos: boolean
}

interface UserOption {
  id: number
  name: string
}

const auth = useAuthStore()
const cart = useCartStore()
const cashRegisterStore = useCashRegisterStore()
const { parse, firstFieldError } = useApiError()
const { maskInput: maskCurrency, toNumber: currencyToNumber, format: formatCurrency } = useCurrencyMask()
const { maskInput: maskCep } = useCepMask()

const loading = ref(true)
const products = ref<Product[]>([])
const paymentMethods = ref<PaymentMethod[]>([])
const users = ref<UserOption[]>([])
const customers = ref<Customer[]>([])
const requireSellerOnSale = ref(false)

async function loadAll() {
  loading.value = true
  const api = useApi()
  const [productsRes, paymentMethodsRes, usersRes, customersRes, storeSettingsRes] = await Promise.all([
    api<{ data: Product[] }>('/products'),
    api<{ data: PaymentMethod[] }>('/payment-methods'),
    api<{ data: UserOption[] }>('/users/active'),
    api<{ data: Customer[] }>('/customers'),
    api<{ data: { require_seller_on_sale: boolean } }>('/store-settings'),
    cashRegisterStore.fetchCurrent(),
  ])
  products.value = productsRes.data
  paymentMethods.value = paymentMethodsRes.data
  users.value = usersRes.data
  customers.value = customersRes.data
  requireSellerOnSale.value = storeSettingsRes.data.require_seller_on_sale

  cart.setSeller(auth.user?.id ?? null)
  loading.value = false
}

await loadAll()

const cashRegisterOpen = computed(() => cashRegisterStore.current !== null)
const quoteOnlyMode = ref(false)

const paymentMethodOptions = computed(() =>
  paymentMethods.value.filter((p) => p.active_on_pos).map((p) => ({ value: p.id, label: p.name })),
)
const sellerOptions = computed(() => users.value.map((u) => ({ value: u.id, label: u.name })))

// ---- Índice de busca por SKU (código/EAN em Map para lookup O(1); nome/código via includes) ----
const skuRows = computed<SkuRow[]>(() => {
  const rows: SkuRow[] = []
  for (const product of products.value) {
    if (!product.active) continue
    for (const variation of product.variations ?? []) {
      const label = [variation.color, variation.size].filter(Boolean).join(' / ') || null
      rows.push({ key: `${variation.id}`, productName: product.name, variationLabel: label, variation })
    }
  }
  return rows
})

const exactMatchIndex = computed(() => {
  const map = new Map<string, SkuRow>()
  for (const row of skuRows.value) {
    if (row.variation.ean_gtin) map.set(row.variation.ean_gtin.toLowerCase(), row)
    map.set(row.variation.product_code.toLowerCase(), row)
  }
  return map
})

function findExact(query: string): SkuRow | null {
  return exactMatchIndex.value.get(query.trim().toLowerCase()) ?? null
}

function findFuzzy(query: string): SkuRow | null {
  const q = query.trim().toLowerCase()
  if (!q) return null
  return skuRows.value.find(
    (row) => row.productName.toLowerCase().includes(q) || row.variation.product_code.toLowerCase().includes(q),
  ) ?? null
}

// ---- Card "Adicionar item" ----
const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const autoAdd = ref(true)
const foundRow = ref<SkuRow | null>(null)
const pendingQty = ref(1)
const pendingUnitMasked = ref('R$ 0,00')

function focusSearch() {
  searchInputRef.value?.focus()
}

function resetSearch() {
  searchQuery.value = ''
  foundRow.value = null
  pendingQty.value = 1
  pendingUnitMasked.value = 'R$ 0,00'
}

function addRowToCart(row: SkuRow, quantity: number, unitPrice?: number) {
  cart.addItem({
    id: row.variation.id,
    productName: row.productName,
    variationLabel: row.variationLabel,
    productCode: row.variation.product_code,
    salePrice: unitPrice ?? Number(row.variation.sale_price),
    currentQuantity: row.variation.current_quantity,
    wholesaleMinQty: row.variation.wholesale_min_qty,
    wholesalePrice: row.variation.wholesale_price !== null ? Number(row.variation.wholesale_price) : null,
  }, quantity)
}

// Autocomplete: sugestões por nome/código enquanto o operador digita (não
// atrapalha o leitor de código de barras — o match exato no Enter continua
// tendo prioridade e inclui na hora, como já funcionava).
const highlightedSuggestionIndex = ref(0)

const searchSuggestions = computed<SkuRow[]>(() => {
  if (foundRow.value) return []
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return []
  return skuRows.value
    .filter((row) => row.productName.toLowerCase().includes(q) || row.variation.product_code.toLowerCase().includes(q))
    .slice(0, 6)
})

watch(searchSuggestions, () => {
  highlightedSuggestionIndex.value = 0
})

function selectSuggestion(row: SkuRow) {
  foundRow.value = row
  searchQuery.value = row.productName
  pendingQty.value = 1
  pendingUnitMasked.value = maskCurrency(String(Math.round(Number(row.variation.sale_price) * 100)))
}

function handleSearchKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown' && searchSuggestions.value.length > 0) {
    event.preventDefault()
    highlightedSuggestionIndex.value = Math.min(highlightedSuggestionIndex.value + 1, searchSuggestions.value.length - 1)
    return
  }
  if (event.key === 'ArrowUp' && searchSuggestions.value.length > 0) {
    event.preventDefault()
    highlightedSuggestionIndex.value = Math.max(highlightedSuggestionIndex.value - 1, 0)
    return
  }
  if (event.key !== 'Enter') return
  event.preventDefault()

  const exact = findExact(searchQuery.value)
  if (exact) {
    if (autoAdd.value) {
      addRowToCart(exact, 1)
      resetSearch()
    } else {
      foundRow.value = exact
      pendingQty.value = 1
      pendingUnitMasked.value = maskCurrency(String(Math.round(Number(exact.variation.sale_price) * 100)))
    }
    return
  }

  const highlighted = searchSuggestions.value[highlightedSuggestionIndex.value]
  if (highlighted) {
    selectSuggestion(highlighted)
    return
  }

  const fuzzy = findFuzzy(searchQuery.value)
  if (fuzzy) {
    foundRow.value = fuzzy
    pendingQty.value = 1
    pendingUnitMasked.value = maskCurrency(String(Math.round(Number(fuzzy.variation.sale_price) * 100)))
  }
}

const pendingTotalFmt = computed(() => formatCurrency(Math.round(currencyToNumber(pendingUnitMasked.value) * pendingQty.value * 100)))

function handleIncluirItem() {
  if (!foundRow.value) return
  addRowToCart(foundRow.value, Math.max(1, pendingQty.value), currencyToNumber(pendingUnitMasked.value))
  resetSearch()
  focusSearch()
}

// ---- Modal de busca de produto (F2) ----
const showProductPicker = ref(false)
const productPickerSearch = ref('')

const filteredProductPickerRows = computed(() => {
  const q = productPickerSearch.value.trim().toLowerCase()
  if (!q) return skuRows.value
  return skuRows.value.filter(
    (row) => row.productName.toLowerCase().includes(q) || row.variation.product_code.toLowerCase().includes(q),
  )
})

function openProductPicker() {
  productPickerSearch.value = ''
  showProductPicker.value = true
}

function chooseProductFromPicker(row: SkuRow) {
  foundRow.value = row
  searchQuery.value = row.productName
  pendingQty.value = 1
  pendingUnitMasked.value = maskCurrency(String(Math.round(Number(row.variation.sale_price) * 100)))
  showProductPicker.value = false
}

// ---- Modal de troca de vendedor/operador (F3) — mesmo padrão do F2 ----
const showOperatorPicker = ref(false)
const operatorPickerSearch = ref('')

const filteredOperatorPickerRows = computed(() => {
  const q = operatorPickerSearch.value.trim().toLowerCase()
  if (!q) return users.value
  return users.value.filter((u) => u.name.toLowerCase().includes(q))
})

const activeSellerName = computed(() => users.value.find((u) => u.id === cart.sellerId)?.name ?? null)

function openOperatorPicker() {
  operatorPickerSearch.value = ''
  showOperatorPicker.value = true
}

function chooseOperatorFromPicker(row: UserOption) {
  cart.setSeller(row.id)
  showOperatorPicker.value = false
}

function handleGlobalKeydown(event: KeyboardEvent) {
  if (event.key === 'F2') {
    event.preventDefault()
    openProductPicker()
    return
  }
  if (event.key === 'F3') {
    event.preventDefault()
    openOperatorPicker()
  }
}

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown))

// Total da linha já líquido de desconto (mesma conta usada no Resumo da venda) — a
// linha do carrinho mostrava só unitário × quantidade, sem refletir o desconto do item.
function itemTotal(item: CartItem): number {
  return lineTotalCents({
    unitPrice: cart.effectivePrice(item),
    quantity: item.quantity,
    discountType: item.discountType,
    discountValue: item.discountValue,
  }) / 100
}

// ---- Configuração do pedido ----
const configOpen = ref(false)

// ---- Cliente ----
const showCustomerPicker = ref(false)
const showNewCustomer = ref(false)
const customerSearch = ref('')

const filteredCustomers = computed(() => {
  const q = customerSearch.value.trim().toLowerCase()
  if (!q) return customers.value
  return customers.value.filter((c) => c.name.toLowerCase().includes(q) || c.mobile_phone.includes(q))
})

function selectCustomer(customer: Customer) {
  cart.setCustomer(customer.id, customer.name)
  showCustomerPicker.value = false
}

function clearCustomer() {
  cart.setCustomer(null, null)
}

function openNewCustomer() {
  showCustomerPicker.value = false
  showNewCustomer.value = true
}

const newCustomerSaving = ref(false)
const newCustomerError = ref<unknown>(null)
function emptyNewCustomerForm() {
  return { name: '', mobile_phone: '', phone: '', email: '', zip_code: '', address: '', neighborhood: '', city: '' }
}
const newCustomerForm = reactive(emptyNewCustomerForm())

async function submitNewCustomer() {
  newCustomerSaving.value = true
  newCustomerError.value = null
  try {
    const api = useApi()
    const { data } = await api<{ data: Customer }>('/customers', {
      method: 'POST',
      body: { ...newCustomerForm, is_company: false },
    })
    customers.value = [data, ...customers.value]
    cart.setCustomer(data.id, data.name)
    showNewCustomer.value = false
    Object.assign(newCustomerForm, emptyNewCustomerForm())
  } catch (err) {
    newCustomerError.value = err
  } finally {
    newCustomerSaving.value = false
  }
}

// ---- Pagamento único (padrão): mesma UX de antes do split payment — só a
// forma, sem campo de valor (é sempre o total). "Dividir pagamento" (toggle)
// é que abre a lista de linhas abaixo.
const valorRecebidoMasked = ref('R$ 0,00')
const troco = computed(() => Math.max(0, currencyToNumber(valorRecebidoMasked.value) - cart.total))

const isCashPayment = computed(() => {
  const method = paymentMethods.value.find((p) => p.id === cart.singlePaymentMethodId)
  return method !== undefined && method.name.trim().toLowerCase() === 'dinheiro'
})

watch(isCashPayment, (isCash) => {
  if (!isCash) valorRecebidoMasked.value = 'R$ 0,00'
})

function usarRestante() {
  valorRecebidoMasked.value = maskCurrency(String(Math.round(cart.total * 100)))
}

function toggleSplitPayment(value: boolean) {
  cart.setSplitPayment(value)
  valorRecebidoMasked.value = 'R$ 0,00'
  // Ao ligar o split com uma forma já escolhida no modo único, aproveita ela
  // na primeira linha em vez de forçar reescolher do zero.
  if (value && cart.singlePaymentMethodId !== null && cart.payments[0]?.paymentMethodId === null) {
    cart.setPaymentMethodAt(0, cart.singlePaymentMethodId)
  }
}

// ---- Pagamento dividido: valor/linha e valor recebido/troco por linha em dinheiro (informativo, não persistido) ----
const paymentAmountMasked = ref<string[]>(['R$ 0,00'])
const cashReceivedMasked = ref<string[]>(['R$ 0,00'])

function addPaymentLine() {
  cart.addPaymentLine()
  paymentAmountMasked.value.push('R$ 0,00')
  cashReceivedMasked.value.push('R$ 0,00')
}

function removePaymentLine(index: number) {
  cart.removePaymentLine(index)
  paymentAmountMasked.value.splice(index, 1)
  cashReceivedMasked.value.splice(index, 1)
}

function setPaymentAmountMasked(index: number, raw: string) {
  const masked = maskCurrency(raw)
  paymentAmountMasked.value[index] = masked
  cart.setPaymentAmountAt(index, currencyToNumber(masked))
}

function setCashReceivedMasked(index: number, raw: string) {
  cashReceivedMasked.value[index] = maskCurrency(raw)
}

function usarRestanteLine(index: number) {
  cart.fillRemainingAt(index)
  const line = cart.payments[index]
  if (line) paymentAmountMasked.value[index] = maskCurrency(String(Math.round(line.amount * 100)))
}

// "Valor recebido"/troco só fazem sentido pra linha em dinheiro — nas demais
// formas (cartão, Pix...) o valor é sempre exato, sem troco a calcular.
// payment_methods é um cadastro livre (Sprint 2), sem um campo que marque
// "isto é dinheiro", então o critério é o nome informado pela loja.
function isCashLine(index: number): boolean {
  const line = cart.payments[index]
  if (!line || line.paymentMethodId === null) return false
  const method = paymentMethods.value.find((p) => p.id === line.paymentMethodId)
  return method !== undefined && method.name.trim().toLowerCase() === 'dinheiro'
}

function trocoFor(index: number): number {
  const line = cart.payments[index]
  if (!line) return 0
  return Math.max(0, currencyToNumber(cashReceivedMasked.value[index] ?? 'R$ 0,00') - line.amount)
}

// ---- Finalizar venda ----
const finalizing = ref(false)
const checkoutError = ref<unknown>(null)

const canFinalize = computed(() => {
  if (!cashRegisterOpen.value || cart.isEmpty) return false
  if (requireSellerOnSale.value && cart.sellerId === null) return false
  if (cart.splitPayment) {
    return cart.payments.every((p) => p.paymentMethodId !== null && p.amount > 0)
      && Math.abs(cart.remainingBalance) < 0.005
  }
  return cart.singlePaymentMethodId !== null
})

async function handleFinalizarVenda() {
  if (!canFinalize.value) return
  finalizing.value = true
  checkoutError.value = null
  try {
    const sale = await cart.checkout()
    valorRecebidoMasked.value = 'R$ 0,00'
    paymentAmountMasked.value = ['R$ 0,00']
    cashReceivedMasked.value = ['R$ 0,00']
    window.open(`/sales/${sale.id}/receipt`, '_blank')
    await cashRegisterStore.fetchCurrent()
    focusSearch()
  } catch (err) {
    checkoutError.value = err
  } finally {
    finalizing.value = false
  }
}

// ---- Salvar orçamento ----
const showQuoteModal = ref(false)
const quoteExpiresAt = ref('')
const savingQuote = ref(false)
const quoteError = ref<unknown>(null)

const canSaveQuote = computed(() => !cart.isEmpty)

function openQuoteModal() {
  quoteExpiresAt.value = ''
  quoteError.value = null
  showQuoteModal.value = true
}

async function confirmSaveQuote() {
  if (!canSaveQuote.value) return
  savingQuote.value = true
  quoteError.value = null
  try {
    cart.setExpiresAt(quoteExpiresAt.value || null)
    await cart.saveAsQuote()
    paymentAmountMasked.value = ['R$ 0,00']
    cashReceivedMasked.value = ['R$ 0,00']
    showQuoteModal.value = false
    await navigateTo('/quotes')
  } catch (err) {
    quoteError.value = err
  } finally {
    savingQuote.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="flex flex-1 items-center justify-center text-sm text-txt-muted">
    Carregando PDV...
  </div>

  <div v-else-if="!cashRegisterOpen && !quoteOnlyMode" class="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
      <ShieldCheck :size="30" />
    </div>
    <div>
      <p class="font-display text-lg font-bold text-txt-primary">Nenhum caixa aberto</p>
      <p class="mt-1 max-w-sm text-sm text-txt-secondary">É preciso abrir o caixa antes de iniciar uma venda no PDV.</p>
    </div>
    <div class="flex items-center gap-4">
      <BaseButton :block="false" @click="navigateTo('/cash-register')">Ir para o Caixa</BaseButton>
      <button type="button" class="cursor-pointer text-sm font-bold text-brand underline" @click="quoteOnlyMode = true">Somente realizar orçamento</button>
    </div>
  </div>

  <template v-else>
    <div class="flex h-[66px] flex-none items-center gap-4 border-b border-border bg-surface-raised px-6">
      <div class="flex flex-none items-center gap-2.5">
        <img src="/logo.png" alt="Logo da loja" class="h-8 w-8 rounded-lg shadow-card">
        <div class="leading-tight">
          <div class="font-display text-sm font-bold text-txt-primary">PDV</div>
          <div class="text-[10px] font-bold tracking-wide text-txt-muted uppercase">Ponto de venda</div>
        </div>
      </div>

      <div class="h-7 w-px flex-none bg-border" />

      <div class="flex-none leading-tight">
        <div class="text-[10px] font-bold tracking-wide text-txt-muted uppercase">Caixa</div>
        <div class="text-[13.5px] font-bold" :class="cashRegisterOpen ? 'text-txt-primary' : 'text-rose-600'">
          {{ cashRegisterOpen ? `Caixa #${cashRegisterStore.current?.id}` : 'Fechado' }}
        </div>
      </div>

      <div class="h-7 w-px flex-none bg-border" />

      <button
        type="button"
        class="flex flex-none cursor-pointer items-center gap-2 rounded-xl px-2 py-1 text-left leading-tight hover:bg-surface-subtle"
        @click="openOperatorPicker"
      >
        <div>
          <div class="flex items-center gap-1.5 text-[10px] font-bold tracking-wide text-txt-muted uppercase">
            Vendedor
            <span class="rounded border border-border px-1 text-[9px] font-bold text-txt-muted">F3</span>
          </div>
          <div class="text-[13.5px] font-bold text-txt-primary">{{ activeSellerName ?? 'Selecionar vendedor' }}</div>
        </div>
      </button>

      <div class="flex-none leading-tight">
        <div class="text-[10px] font-bold tracking-wide text-txt-muted uppercase">Sessão</div>
        <div class="text-[13.5px] font-bold text-txt-secondary">{{ auth.user?.name ?? '-' }}</div>
      </div>

      <div
        class="flex min-w-0 max-w-xs flex-1 items-center gap-2.5 rounded-xl border px-3.5 py-2"
        :class="cart.customerId ? 'border-emerald-200 bg-emerald-50' : 'border-border bg-surface-subtle'"
      >
        <UserIcon :size="15" class="flex-none text-txt-muted" />
        <div class="min-w-0 overflow-hidden">
          <div class="truncate text-[9.5px] font-bold tracking-wide text-txt-muted uppercase">Cliente</div>
          <div class="truncate text-[13px] font-bold text-txt-primary">{{ cart.customerName ?? 'Nenhum cliente vinculado' }}</div>
        </div>
        <button v-if="cart.customerId" type="button" class="ml-auto flex-none text-txt-muted hover:text-txt-primary" @click="clearCustomer">
          <X :size="13" />
        </button>
      </div>

      <BaseButton variant="ghost" :block="false" @click="showCustomerPicker = true">
        <Search :size="14" />
        Trocar
      </BaseButton>
      <BaseButton variant="ghost" :block="false" @click="openNewCustomer">
        <UserPlus :size="14" />
        Novo cliente
      </BaseButton>

      <div class="flex-1" />

      <button
        type="button"
        class="flex flex-none cursor-pointer items-center gap-2 rounded-full bg-rose-600 px-4 py-2 text-[12.5px] font-bold text-white transition hover:brightness-95"
        @click="navigateTo('/')"
      >
        <LogOut :size="14" />
        Sair
      </button>
    </div>

    <div class="flex flex-1 min-h-0">
      <!-- LEFT -->
      <div class="flex w-[324px] flex-none flex-col gap-3.5 overflow-y-auto border-r border-border p-4">
        <div class="rounded-2xl bg-surface-raised p-4 shadow-card">
          <p class="font-display text-sm font-bold text-txt-primary">Adicionar item</p>
          <p class="mb-3 text-[10px] font-bold tracking-wide text-txt-muted uppercase">Bipe ou busque o produto</p>

          <label class="relative mb-3 block">
            <Search :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-txt-muted" />
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              placeholder="Código, nome ou código de barra"
              autofocus
              autocomplete="off"
              class="w-full rounded-xl border-[1.5px] border-brand bg-surface-raised py-2.5 pr-3 pl-9 text-sm text-txt-primary focus:outline-none"
              @keydown="handleSearchKeydown"
            >

            <div
              v-if="searchSuggestions.length > 0"
              class="absolute top-full right-0 left-0 z-10 mt-1.5 overflow-hidden rounded-xl border border-border bg-surface-raised shadow-card"
            >
              <button
                v-for="(row, index) in searchSuggestions"
                :key="row.key"
                type="button"
                class="flex w-full cursor-pointer items-center justify-between gap-2 px-3.5 py-2.5 text-left"
                :class="index === highlightedSuggestionIndex ? 'bg-brand/15' : 'hover:bg-surface-subtle'"
                @mouseenter="highlightedSuggestionIndex = index"
                @click="selectSuggestion(row)"
              >
                <div class="min-w-0">
                  <p class="truncate text-[13px] font-bold text-txt-primary">{{ row.productName }}</p>
                  <p class="text-[11px] text-txt-muted">Cód. {{ row.variation.product_code }}<span v-if="row.variationLabel"> · {{ row.variationLabel }}</span></p>
                </div>
                <span class="flex-none text-[12.5px] font-bold text-txt-secondary">{{ formatCurrency(Math.round(Number(row.variation.sale_price) * 100)) }}</span>
              </button>
            </div>
          </label>

          <div v-if="foundRow" class="mb-3 rounded-xl border border-border p-3">
            <p class="text-[13.5px] font-bold text-txt-primary">{{ foundRow.productName }}</p>
            <p class="mb-3 text-[11.5px] text-txt-muted">Cód. {{ foundRow.variation.product_code }}</p>

            <div class="mb-2.5 grid grid-cols-2 gap-2.5">
              <div>
                <label class="text-[9.5px] font-bold tracking-wide text-txt-muted uppercase">Qtd.</label>
                <div class="mt-1.5 flex items-center gap-1.5">
                  <button type="button" class="flex h-7 w-7 flex-none items-center justify-center rounded-lg border border-border text-txt-secondary" @click="pendingQty = Math.max(1, pendingQty - 1)">
                    <Minus :size="13" />
                  </button>
                  <input v-model.number="pendingQty" type="number" min="1" class="w-full rounded-lg border border-border px-1 py-1 text-center text-sm">
                  <button type="button" class="flex h-7 w-7 flex-none items-center justify-center rounded-lg border border-border text-txt-secondary" @click="pendingQty += 1">
                    <Plus :size="13" />
                  </button>
                </div>
              </div>
              <div>
                <label class="text-[9.5px] font-bold tracking-wide text-txt-muted uppercase">Unitário</label>
                <input
                  :value="pendingUnitMasked"
                  type="text"
                  class="mt-1.5 w-full rounded-lg border border-border px-2 py-1.5 text-sm"
                  @input="pendingUnitMasked = maskCurrency(($event.target as HTMLInputElement).value)"
                >
              </div>
            </div>

            <div class="mb-3 flex items-center justify-between">
              <span class="text-[12.5px] text-txt-muted">Total do item</span>
              <span class="text-[15px] font-bold text-emerald-600">{{ pendingTotalFmt }}</span>
            </div>

            <BaseButton :block="true" @click="handleIncluirItem">
              <Plus :size="15" />
              Incluir item
            </BaseButton>
          </div>

          <label class="flex cursor-pointer items-center gap-2">
            <input v-model="autoAdd" type="checkbox" class="h-4 w-4 rounded border-border text-brand focus:ring-brand">
            <span class="text-[12.5px] font-semibold text-txt-secondary">Incluir automático após leitura</span>
          </label>
        </div>

        <a href="/products" target="_blank" class="flex items-center gap-2.5 rounded-2xl bg-surface-raised p-4 shadow-card transition hover:shadow-md">
          <div class="flex h-7.5 w-7.5 flex-none items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
            <Plus :size="15" />
          </div>
          <span class="text-[13px] font-bold text-txt-secondary">Cadastrar novo produto</span>
        </a>

        <div class="flex-1" />

        <div class="overflow-hidden rounded-2xl bg-surface-raised shadow-card">
          <button type="button" class="flex w-full cursor-pointer items-center justify-between p-4" @click="configOpen = !configOpen">
            <div class="text-left">
              <p class="text-[13px] font-bold text-txt-primary">Configuração do pedido</p>
              <p class="text-[10px] font-bold tracking-wide text-txt-muted uppercase">Observações e vendedor</p>
            </div>
            <ChevronDown :size="14" class="text-txt-muted transition-transform" :class="{ '-rotate-90': !configOpen }" />
          </button>
          <div v-if="configOpen" class="space-y-2.5 px-4 pb-4">
            <BaseSelect
              :model-value="cart.sellerId"
              label="Vendedor"
              :options="sellerOptions"
              :error="requireSellerOnSale ? firstFieldError(checkoutError, 'seller_id') : null"
              @update:model-value="cart.setSeller(Number($event))"
            />
            <BaseTextarea :model-value="cart.notes ?? ''" label="Observação" :rows="2" @update:model-value="cart.setNotes($event || null)" />
          </div>
        </div>
      </div>

      <!-- CENTER -->
      <div class="flex min-w-0 flex-1 flex-col gap-3.5 overflow-y-auto p-4">
        <div class="flex min-h-0 flex-1 flex-col rounded-2xl bg-surface-raised shadow-card">
          <div class="flex flex-none items-center justify-between border-b border-border px-5 py-4">
            <div class="flex items-center gap-2.5">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                <Boxes :size="15" />
              </div>
              <div>
                <p class="font-display text-sm font-bold text-txt-primary">Carrinho</p>
                <p class="text-[10px] font-bold tracking-wide text-txt-muted uppercase">Itens e quantidades</p>
              </div>
            </div>
            <span class="rounded-full bg-surface-subtle px-3 py-1 text-xs font-bold text-txt-secondary">
              {{ cart.itemCount }} {{ cart.itemCount === 1 ? 'item' : 'itens' }}
            </span>
          </div>

          <div v-if="cart.isEmpty" class="flex flex-1 flex-col items-center justify-center gap-2 p-10 text-center">
            <div class="mb-2 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
              <Boxes :size="30" />
            </div>
            <p class="font-display text-base font-bold text-txt-primary">Carrinho vazio</p>
            <p class="max-w-xs text-sm text-txt-muted">Bipe um código de barras ou busque o primeiro produto ao lado.</p>
          </div>

          <div v-else class="flex-1 overflow-x-auto overflow-y-auto px-2 py-1">
            <div
              v-for="item in cart.items"
              :key="item.key"
              class="grid grid-cols-[minmax(150px,1fr)_auto_auto_auto_auto] items-center gap-3 border-b border-border/60 px-3 py-3 last:border-0"
            >
              <div class="min-w-0">
                <p class="truncate text-[16px] font-bold text-txt-primary">{{ item.productName }}</p>
                <p class="truncate text-[13.5px] text-txt-muted">Cód. {{ item.productCode }}<span v-if="item.variationLabel"> · {{ item.variationLabel }}</span></p>
                <label
                  v-if="item.wholesaleMinQty !== null && item.wholesalePrice !== null"
                  class="mt-1 flex items-center gap-1.5"
                  :class="item.quantity >= item.wholesaleMinQty ? 'cursor-pointer' : 'cursor-not-allowed opacity-50'"
                >
                  <input
                    type="checkbox"
                    :checked="item.applyWholesale"
                    :disabled="item.quantity < item.wholesaleMinQty"
                    class="h-3.5 w-3.5 rounded border-border text-brand focus:ring-brand"
                    @change="cart.setItemWholesale(item.key, ($event.target as HTMLInputElement).checked)"
                  >
                  <span class="text-[11.5px] font-semibold text-txt-secondary">
                    Atacado ({{ formatCurrency(Math.round(item.wholesalePrice * 100)) }} a partir de {{ item.wholesaleMinQty }} un.)
                  </span>
                </label>
              </div>
              <div class="flex flex-none items-center gap-1.5">
                <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg border border-border text-txt-secondary" @click="cart.updateQuantity(item.key, item.quantity - 1)">
                  <Minus :size="13" />
                </button>
                <span class="w-7 text-center text-[17px] font-bold text-txt-primary">{{ item.quantity }}</span>
                <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg border border-border text-txt-secondary" @click="cart.updateQuantity(item.key, item.quantity + 1)">
                  <Plus :size="13" />
                </button>
              </div>
              <div class="w-44 flex-none">
                <DiscountInput
                  :type="item.discountType"
                  :value="item.discountValue"
                  @update:type="cart.updateItemDiscount(item.key, $event, item.discountValue)"
                  @update:value="cart.updateItemDiscount(item.key, item.discountType, $event)"
                />
              </div>
              <span class="w-24 flex-none text-right text-[17px] font-bold text-txt-primary">
                {{ formatCurrency(Math.round(itemTotal(item) * 100)) }}
              </span>
              <button type="button" class="flex h-7.5 w-7.5 flex-none items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50" @click="cart.removeItem(item.key)">
                <Trash2 :size="15" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT -->
      <div class="flex w-[440px] flex-none flex-col gap-3.5 overflow-y-auto border-l border-border p-4">
        <div class="rounded-2xl bg-surface-raised p-4.5 shadow-card">
          <p class="font-display text-sm font-bold text-txt-primary">Resumo da venda</p>
          <p class="mb-4 text-[10px] font-bold tracking-wide text-txt-muted uppercase">Valores e descontos</p>

          <div class="mb-3 flex items-center justify-between">
            <span class="text-sm text-txt-secondary">Subtotal</span>
            <span class="text-[13.5px] font-semibold text-txt-primary">{{ formatCurrency(Math.round(cart.subtotal * 100)) }}</span>
          </div>
          <div class="mb-3 flex items-center justify-between gap-3">
            <span class="text-sm text-txt-secondary">Desconto</span>
            <div class="w-44">
              <DiscountInput
                :type="cart.saleDiscountType"
                :value="cart.saleDiscountValue"
                @update:type="cart.setSaleDiscount($event, cart.saleDiscountValue)"
                @update:value="cart.setSaleDiscount(cart.saleDiscountType, $event)"
              />
            </div>
          </div>

          <div class="my-3 h-px bg-border" />

          <span class="text-[10px] font-bold tracking-wide text-txt-muted uppercase">Total a pagar</span>
          <div class="font-display text-[36px] font-extrabold text-emerald-600">{{ formatCurrency(Math.round(cart.total * 100)) }}</div>
        </div>

        <div v-if="cashRegisterOpen" class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3.5 py-3 text-emerald-700">
          <ShieldCheck :size="16" class="flex-none" />
          <span class="text-xs font-semibold">Estoque sincronizado · venda protegida</span>
        </div>
        <div v-else class="flex items-center gap-2 rounded-xl bg-rose-50 px-3.5 py-3 text-rose-700">
          <ShieldCheck :size="16" class="flex-none" />
          <span class="text-xs font-semibold">
            Nenhum caixa aberto — dá pra montar um orçamento, mas é preciso
            <button type="button" class="underline" @click="navigateTo('/cash-register')">abrir o caixa</button>
            para finalizar uma venda.
          </span>
        </div>

        <div v-if="cashRegisterOpen" class="rounded-2xl bg-surface-raised p-4.5 shadow-card">
          <div class="mb-3 flex items-center justify-between">
            <p class="font-display text-sm font-bold text-txt-primary">Forma de pagamento</p>
            <BaseSwitch :model-value="cart.splitPayment" label="Dividir pagamento" @update:model-value="toggleSplitPayment" />
          </div>

          <template v-if="!cart.splitPayment">
            <BaseSelect
              :model-value="cart.singlePaymentMethodId"
              label="Forma de pagamento"
              :options="paymentMethodOptions"
              :error="firstFieldError(checkoutError, 'payments.0.payment_method_id')"
              @update:model-value="cart.setSinglePaymentMethod(Number($event))"
            />

            <template v-if="isCashPayment">
              <div class="mt-3">
                <label class="mb-1 block text-sm font-medium text-txt-secondary">Valor recebido</label>
                <input
                  :value="valorRecebidoMasked"
                  type="text"
                  class="w-full rounded-xl border border-border px-3 py-2 text-sm"
                  @input="valorRecebidoMasked = maskCurrency(($event.target as HTMLInputElement).value)"
                >
                <div class="mt-1.5 flex justify-end">
                  <button
                    type="button"
                    class="cursor-pointer rounded-full border border-brand/30 bg-brand/10 px-2.5 py-1 text-[11px] font-bold text-brand transition hover:bg-brand/20"
                    @click="usarRestante"
                  >
                    Usar restante
                  </button>
                </div>
              </div>

              <div v-if="troco > 0" class="mt-2 flex items-center justify-between text-sm">
                <span class="text-txt-secondary">Troco</span>
                <span class="font-bold text-txt-primary">{{ formatCurrency(Math.round(troco * 100)) }}</span>
              </div>
            </template>
          </template>

          <template v-else>
            <div class="mb-3 flex items-center justify-end">
              <span class="text-[11.5px] font-bold" :class="cart.remainingBalance === 0 ? 'text-emerald-600' : 'text-rose-600'">
                {{ cart.remainingBalance === 0 ? 'Pagamento completo' : `Restante: ${formatCurrency(Math.round(cart.remainingBalance * 100))}` }}
              </span>
            </div>

            <div v-for="(line, index) in cart.payments" :key="index" class="mb-3 rounded-xl border border-border p-3">
              <div class="flex items-end gap-2">
                <div class="flex-1">
                  <BaseSelect
                    :model-value="line.paymentMethodId"
                    label="Forma"
                    :options="paymentMethodOptions"
                    :error="firstFieldError(checkoutError, `payments.${index}.payment_method_id`)"
                    @update:model-value="cart.setPaymentMethodAt(index, Number($event))"
                  />
                </div>
                <div class="w-32 flex-none">
                  <label class="mb-1 block text-sm font-medium text-txt-secondary">Valor</label>
                  <input
                    :value="paymentAmountMasked[index]"
                    type="text"
                    class="w-full rounded-xl border border-border px-3 py-2 text-sm"
                    @input="setPaymentAmountMasked(index, ($event.target as HTMLInputElement).value)"
                  >
                </div>
                <button
                  v-if="cart.payments.length > 1"
                  type="button"
                  class="mb-0.5 flex h-9 w-9 flex-none items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50"
                  @click="removePaymentLine(index)"
                >
                  <Trash2 :size="15" />
                </button>
              </div>

              <div class="mt-1.5 flex justify-end">
                <button
                  type="button"
                  class="cursor-pointer rounded-full border border-brand/30 bg-brand/10 px-2.5 py-1 text-[11px] font-bold text-brand transition hover:bg-brand/20"
                  @click="usarRestanteLine(index)"
                >
                  Usar restante
                </button>
              </div>

              <template v-if="isCashLine(index)">
                <div class="mt-2">
                  <label class="mb-1 block text-sm font-medium text-txt-secondary">Valor recebido</label>
                  <input
                    :value="cashReceivedMasked[index]"
                    type="text"
                    class="w-full rounded-xl border border-border px-3 py-2 text-sm"
                    @input="setCashReceivedMasked(index, ($event.target as HTMLInputElement).value)"
                  >
                </div>

                <div v-if="trocoFor(index) > 0" class="mt-1.5 flex items-center justify-between text-sm">
                  <span class="text-txt-secondary">Troco</span>
                  <span class="font-bold text-txt-primary">{{ formatCurrency(Math.round(trocoFor(index) * 100)) }}</span>
                </div>
              </template>
            </div>

            <BaseButton variant="ghost" :block="true" @click="addPaymentLine">
              <Plus :size="14" />
              Adicionar forma de pagamento
            </BaseButton>
          </template>
        </div>

        <p v-if="checkoutError" class="text-sm text-rose-600">{{ parse(checkoutError).message }}</p>

        <div class="flex-1" />

        <BaseButton variant="ghost" :disabled="!canSaveQuote" @click="openQuoteModal">
          Salvar Orçamento
        </BaseButton>
        <BaseButton :loading="finalizing" :disabled="!canFinalize" @click="handleFinalizarVenda">
          Finalizar Venda
        </BaseButton>
      </div>
    </div>

    <!-- MODAL: TROCAR CLIENTE -->
    <BaseModal :open="showCustomerPicker" title="Selecionar cliente" @close="showCustomerPicker = false">
      <label class="relative mb-3 block">
        <Search :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-txt-muted" />
        <input v-model="customerSearch" type="text" placeholder="Buscar por nome ou celular" class="w-full rounded-xl border border-border py-2.5 pr-3 pl-9 text-sm">
      </label>
      <div class="max-h-80 space-y-1 overflow-y-auto">
        <button
          v-for="customer in filteredCustomers"
          :key="customer.id"
          type="button"
          class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left hover:bg-surface-subtle"
          @click="selectCustomer(customer)"
        >
          <div class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-surface-subtle text-xs font-bold text-txt-secondary">
            {{ customer.name.slice(0, 2).toUpperCase() }}
          </div>
          <div>
            <p class="text-sm font-bold text-txt-primary">{{ customer.name }}</p>
            <p class="text-[11.5px] text-txt-muted">{{ customer.mobile_phone }}</p>
          </div>
        </button>
        <p v-if="filteredCustomers.length === 0" class="py-6 text-center text-sm text-txt-muted">Nenhum cliente encontrado.</p>
      </div>
      <button type="button" class="mt-2 flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-brand hover:bg-surface-subtle" @click="openNewCustomer">
        <Plus :size="15" />
        <span class="text-sm font-bold">Novo cliente</span>
      </button>
    </BaseModal>

    <!-- MODAL: BUSCAR PRODUTO (F2) -->
    <BaseModal :open="showProductPicker" size="lg" title="Buscar produto" subtitle="Busque por nome ou código e escolha o item." @close="showProductPicker = false">
      <label class="relative mb-3 block">
        <Search :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-txt-muted" />
        <input
          v-model="productPickerSearch"
          type="text"
          placeholder="Nome ou código do produto"
          autofocus
          class="w-full rounded-xl border border-border py-2.5 pr-3 pl-9 text-sm"
        >
      </label>
      <div class="max-h-96 space-y-1 overflow-y-auto">
        <div
          v-for="row in filteredProductPickerRows"
          :key="row.key"
          class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 hover:bg-surface-subtle"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-bold text-txt-primary">{{ row.productName }}</p>
            <p class="text-[11.5px] text-txt-muted">
              Cód. {{ row.variation.product_code }}<span v-if="row.variationLabel"> · {{ row.variationLabel }}</span>
              · {{ formatCurrency(Math.round(Number(row.variation.sale_price) * 100)) }}
            </p>
          </div>
          <BaseButton :block="false" @click="chooseProductFromPicker(row)">Escolher</BaseButton>
        </div>
        <p v-if="filteredProductPickerRows.length === 0" class="py-6 text-center text-sm text-txt-muted">Nenhum produto encontrado.</p>
      </div>
    </BaseModal>

    <!-- MODAL: TROCAR VENDEDOR (F3) -->
    <BaseModal :open="showOperatorPicker" title="Trocar vendedor" subtitle="Busque por nome e escolha quem está atendendo agora." @close="showOperatorPicker = false">
      <label class="relative mb-3 block">
        <Search :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-txt-muted" />
        <input
          v-model="operatorPickerSearch"
          type="text"
          placeholder="Nome do vendedor"
          autofocus
          class="w-full rounded-xl border border-border py-2.5 pr-3 pl-9 text-sm"
        >
      </label>
      <div class="max-h-96 space-y-1 overflow-y-auto">
        <div
          v-for="row in filteredOperatorPickerRows"
          :key="row.id"
          class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 hover:bg-surface-subtle"
        >
          <p class="truncate text-sm font-bold text-txt-primary">{{ row.name }}</p>
          <BaseButton :block="false" @click="chooseOperatorFromPicker(row)">Escolher</BaseButton>
        </div>
        <p v-if="filteredOperatorPickerRows.length === 0" class="py-6 text-center text-sm text-txt-muted">Nenhum vendedor encontrado.</p>
      </div>
    </BaseModal>

    <!-- MODAL: SALVAR ORÇAMENTO -->
    <BaseModal :open="showQuoteModal" title="Salvar orçamento" subtitle="O carrinho é salvo como orçamento — não baixa estoque nem lança no caixa." @close="showQuoteModal = false">
      <div class="space-y-4">
        <BaseInput v-model="quoteExpiresAt" type="date" label="Validade (opcional)" :error="firstFieldError(quoteError, 'expires_at')" />
        <p v-if="quoteError && !firstFieldError(quoteError, 'expires_at')" class="text-sm text-rose-600">{{ parse(quoteError).message }}</p>
        <div class="flex justify-end gap-3 border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="showQuoteModal = false">Cancelar</BaseButton>
          <BaseButton type="button" :loading="savingQuote" :block="false" @click="confirmSaveQuote">Salvar orçamento</BaseButton>
        </div>
      </div>
    </BaseModal>

    <!-- MODAL: NOVO CLIENTE -->
    <BaseModal :open="showNewCustomer" title="Novo cliente" subtitle="Cadastro rápido a partir do PDV." @close="showNewCustomer = false">
      <form class="space-y-3.5" @submit.prevent="submitNewCustomer">
        <BaseInput v-model="newCustomerForm.name" label="Nome" :error="firstFieldError(newCustomerError, 'name')" />
        <div class="grid grid-cols-2 gap-3.5">
          <BaseInput v-model="newCustomerForm.mobile_phone" label="Celular" :error="firstFieldError(newCustomerError, 'mobile_phone')" />
          <BaseInput v-model="newCustomerForm.phone" label="Telefone" :error="firstFieldError(newCustomerError, 'phone')" />
        </div>
        <BaseInput v-model="newCustomerForm.email" label="E-mail" :error="firstFieldError(newCustomerError, 'email')" />
        <div class="grid grid-cols-3 gap-3.5">
          <BaseInput :model-value="newCustomerForm.zip_code" label="CEP" @update:model-value="newCustomerForm.zip_code = maskCep($event)" />
          <div class="col-span-2">
            <BaseInput v-model="newCustomerForm.address" label="Endereço" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3.5">
          <BaseInput v-model="newCustomerForm.neighborhood" label="Bairro" />
          <BaseInput v-model="newCustomerForm.city" label="Cidade" />
        </div>

        <p v-if="newCustomerError" class="text-sm text-rose-600">{{ parse(newCustomerError).message }}</p>

        <div class="flex justify-end gap-3 border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="showNewCustomer = false">Cancelar</BaseButton>
          <BaseButton type="submit" :loading="newCustomerSaving" :block="false">Salvar</BaseButton>
        </div>
      </form>
    </BaseModal>
  </template>
</template>
