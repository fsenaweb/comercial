<script setup lang="ts">
import { ArrowLeft, Eye, Layers, Plus, Search, ShoppingCart, Trash2, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next'
import type { CashOperationOrigin, CashRegister } from '~/stores/cashRegister'

interface PaymentMethod {
  id: number
  name: string
  active_on_pos: boolean
}

interface SaleItemDetail {
  id: number
  product_name: string | null
  product_code: string | null
  quantity: number
  unit_price: string
  discount: string
  total: string
}

interface SaleDetail {
  id: number
  number: string
  customer_name: string | null
  seller_name: string | null
  subtotal: string
  discount: string
  total: string
  payment_method_name: string | null
  items: SaleItemDetail[]
}

const store = useCashRegisterStore()
const auth = useAuthStore()
const { parse, firstFieldError } = useApiError()
const { maskInput: maskCurrency, toNumber, format } = useCurrencyMask()

const canOperate = computed(() => auth.user?.role === 'admin' || auth.user?.role === 'cashier')

function formatAmount(value: string | number | null): string {
  if (value === null) return '—'
  return format(Math.round(Number(value) * 100))
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const loading = ref(true)
const view = ref<'list' | 'open' | 'detail'>('list')
const filterStatus = ref<'open' | 'closed'>('open')
const search = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const selectedId = ref<number | null>(null)
const paymentMethods = ref<PaymentMethod[]>([])

const registers = computed(() => store.registers)
const selected = computed(() => registers.value.find((r) => r.id === selectedId.value) ?? null)

async function loadPaymentMethods() {
  const api = useApi()
  const { data } = await api<{ data: PaymentMethod[] }>('/payment-methods')
  paymentMethods.value = data
}

async function loadAll() {
  loading.value = true
  await Promise.all([store.fetchList(), store.fetchCurrent(), loadPaymentMethods()])
  if (store.current) {
    await store.fetchOperations(store.current.id)
    selectedId.value = store.current.id
  }
  loading.value = false
}

const filteredRegisters = computed(() => {
  const q = search.value.trim().toLowerCase()
  return registers.value.filter((r) => {
    const matchesStatus = r.status === filterStatus.value
    const matchesSearch = q === '' || String(r.id).includes(q) || (r.notes ?? '').toLowerCase().includes(q)
    const openedDate = r.opened_at.slice(0, 10)
    const matchesFrom = dateFrom.value === '' || openedDate >= dateFrom.value
    const matchesTo = dateTo.value === '' || openedDate <= dateTo.value
    return matchesStatus && matchesSearch && matchesFrom && matchesTo
  })
})

const countOpen = computed(() => registers.value.filter((r) => r.status === 'open').length)
const countClosed = computed(() => registers.value.filter((r) => r.status === 'closed').length)

const kpiEntradas = computed(() => store.operations.filter((o) => o.type === 'in').reduce((sum, o) => sum + Number(o.amount), 0))
const kpiSaidas = computed(() => store.operations.filter((o) => o.type === 'out').reduce((sum, o) => sum + Number(o.amount), 0))
const kpiSaldoTotal = computed(() => (store.current ? Number(store.current.expected_amount) : 0))

// ---- Detalhe / edição do caixa ----
const detailOpeningAmount = ref('')
const detailNotes = ref<string | null>(null)
const detailClosingAmount = ref('')
const saveSaving = ref(false)
const saveError = ref<unknown>(null)
const closeSaving = ref(false)
const closeError = ref<unknown>(null)

function syncDetailFields(register: CashRegister) {
  detailOpeningAmount.value = formatAmount(register.opening_amount)
  detailNotes.value = register.notes
  detailClosingAmount.value = register.closing_amount ? formatAmount(register.closing_amount) : formatAmount(register.expected_amount)
}

// ---- Abrir novo caixa ----
const openAmount = ref('')
const openNotes = ref<string | null>(null)
const openSaving = ref(false)
const openError = ref<unknown>(null)

function startOpen() {
  openAmount.value = ''
  openNotes.value = null
  openError.value = null
  view.value = 'open'
}

async function handleOpen() {
  openSaving.value = true
  openError.value = null

  try {
    const register = await store.open(toNumber(openAmount.value), openNotes.value)
    await store.fetchList()
    await store.fetchOperations(register.id)
    selectedId.value = register.id
    syncDetailFields(register)
    view.value = 'detail'
  } catch (err) {
    openError.value = err
  } finally {
    openSaving.value = false
  }
}

async function openDetail(register: CashRegister) {
  selectedId.value = register.id
  syncDetailFields(register)
  saveError.value = null
  closeError.value = null
  view.value = 'detail'
  await store.fetchOperations(register.id)
}

async function backToList() {
  view.value = 'list'
  await store.fetchList()
  if (store.current) {
    await store.fetchOperations(store.current.id)
    selectedId.value = store.current.id
  }
}

async function handleSave() {
  if (!selected.value) return
  saveSaving.value = true
  saveError.value = null

  try {
    await store.update(selected.value.id, toNumber(detailOpeningAmount.value), detailNotes.value)
    await store.fetchList()
  } catch (err) {
    saveError.value = err
  } finally {
    saveSaving.value = false
  }
}

async function handleClose() {
  if (!selected.value) return
  if (!confirm(`Confirma o fechamento do caixa #${selected.value.id}?`)) return

  closeSaving.value = true
  closeError.value = null

  try {
    await store.close(selected.value.id, toNumber(detailClosingAmount.value), detailNotes.value)
    await store.fetchList()
    await backToList()
  } catch (err) {
    closeError.value = err
  } finally {
    closeSaving.value = false
  }
}

// ---- Nova operação (sangria/reforço) ----
const novaOpTipo = ref('Entrada')
const novaOpValor = ref('')
const novaOpPaymentMethodId = ref<string | number>('')
const novaOpNotes = ref<string | null>(null)
const operationSaving = ref(false)
const operationError = ref<unknown>(null)

const paymentMethodOptions = computed(() => paymentMethods.value.map((pm) => ({ value: pm.id, label: pm.name })))

async function handleAddOperation() {
  if (!selected.value) return
  operationSaving.value = true
  operationError.value = null

  try {
    const origin: CashOperationOrigin = novaOpTipo.value === 'Entrada' ? 'cash_reinforcement' : 'cash_withdrawal'
    await store.registerOperation(origin, toNumber(novaOpValor.value), novaOpPaymentMethodId.value || null, novaOpNotes.value)
    await Promise.all([store.fetchList(), store.fetchCurrent()])
    novaOpValor.value = ''
    novaOpPaymentMethodId.value = ''
    novaOpNotes.value = null
  } catch (err) {
    operationError.value = err
  } finally {
    operationSaving.value = false
  }
}

async function handleRemoveOperation(operationId: number) {
  if (!confirm('Remover este lançamento?')) return
  await store.removeOperation(operationId)
  await Promise.all([store.fetchList(), store.fetchCurrent()])
}

// ---- Ver itens de uma venda (a partir da operação de caixa) ----
const showSaleDetail = ref(false)
const saleDetailLoading = ref(false)
const saleDetail = ref<SaleDetail | null>(null)

async function viewSaleItems(saleId: number) {
  showSaleDetail.value = true
  saleDetailLoading.value = true
  saleDetail.value = null
  try {
    const api = useApi()
    const { data } = await api<{ data: SaleDetail }>(`/sales/${saleId}`)
    saleDetail.value = data
  } finally {
    saleDetailLoading.value = false
  }
}

await loadAll()
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Caixa</h1>
      <p class="text-sm text-txt-secondary">Acompanhe aberturas, movimentações, saldo e operações do caixa em um fluxo único.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard label="Saldo total" :value="formatAmount(kpiSaldoTotal)" :subtext="`${store.current ? 1 : 0} caixa(s) aberto(s)`" :icon="Wallet" tone="emerald" />
      <StatCard label="Entradas" :value="formatAmount(kpiEntradas)" subtext="Total de entradas" :icon="TrendingUp" tone="sky" />
      <StatCard label="Saídas" :value="formatAmount(kpiSaidas)" subtext="Total de saídas" :icon="TrendingDown" tone="danger" />
      <StatCard label="Caixas" :value="registers.length" :subtext="`${countOpen} aberto(s) / ${countClosed} fechado(s)`" :icon="Layers" tone="violet" />
    </div>

    <!-- LIST VIEW -->
    <div v-if="view === 'list'" class="space-y-4">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 class="font-display text-xl font-bold text-txt-primary">Caixas</h2>
          <p class="text-sm text-txt-secondary">Acompanhe aberturas, fechamentos e operações do caixa.</p>
        </div>
        <BaseButton v-if="canOperate" :block="false" :disabled="!!store.current" @click="startOpen">
          <Plus :size="15" />
          Novo caixa
        </BaseButton>
      </div>

      <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card">
        <div class="flex gap-1 rounded-full bg-surface-subtle p-1">
          <button
            type="button"
            class="flex cursor-pointer items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-bold transition"
            :class="filterStatus === 'open' ? 'bg-emerald-600 text-white' : 'text-txt-secondary'"
            @click="filterStatus = 'open'"
          >
            Abertos
            <span class="inline-flex h-4.5 min-w-4.5 items-center justify-center rounded-full px-1 text-[11px]" :class="filterStatus === 'open' ? 'bg-white/25 text-white' : 'bg-border text-txt-secondary'">{{ countOpen }}</span>
          </button>
          <button
            type="button"
            class="flex cursor-pointer items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-bold transition"
            :class="filterStatus === 'closed' ? 'bg-emerald-600 text-white' : 'text-txt-secondary'"
            @click="filterStatus = 'closed'"
          >
            Fechados
            <span class="inline-flex h-4.5 min-w-4.5 items-center justify-center rounded-full px-1 text-[11px]" :class="filterStatus === 'closed' ? 'bg-white/25 text-white' : 'bg-border text-txt-secondary'">{{ countClosed }}</span>
          </button>
        </div>
        <label class="flex flex-1 items-center gap-2 rounded-full border border-border bg-surface px-3.5 py-2 text-txt-muted" style="min-width: 220px">
          <Search :size="15" />
          <input v-model="search" type="text" placeholder="Buscar por ID ou observação" class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none">
        </label>
        <label class="flex items-center gap-2 rounded-full border border-border bg-surface px-3.5 py-2 text-txt-muted">
          <span class="text-xs font-semibold whitespace-nowrap">De</span>
          <input v-model="dateFrom" type="date" class="bg-transparent text-sm text-txt-primary focus:outline-none">
        </label>
        <label class="flex items-center gap-2 rounded-full border border-border bg-surface px-3.5 py-2 text-txt-muted">
          <span class="text-xs font-semibold whitespace-nowrap">Até</span>
          <input v-model="dateTo" type="date" class="bg-transparent text-sm text-txt-primary focus:outline-none">
        </label>
      </div>

      <span class="block text-xs text-txt-secondary">
        Exibindo <strong class="text-txt-primary">{{ filteredRegisters.length }}</strong> de <strong class="text-txt-primary">{{ registers.length }}</strong> caixa(s)
      </span>

      <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
        <div class="grid grid-cols-[0.6fr_1.4fr_1fr_1fr_1fr_0.9fr_1.1fr_60px] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
          <span>ID</span>
          <span>Data</span>
          <span>Abertura</span>
          <span>Valor atual</span>
          <span>Fechamento</span>
          <span>Status</span>
          <span>Operador</span>
          <span class="text-right">Ações</span>
        </div>

        <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
        <div v-else-if="filteredRegisters.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">
          Nenhum caixa encontrado para o filtro selecionado.
        </div>
        <div
          v-for="register in filteredRegisters"
          v-else
          :key="register.id"
          class="grid grid-cols-[0.6fr_1.4fr_1fr_1fr_1fr_0.9fr_1.1fr_60px] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
        >
          <span class="text-sm font-bold text-emerald-700">#{{ register.id }}</span>
          <span class="text-sm text-txt-secondary">{{ formatDateTime(register.opened_at) }}</span>
          <span class="text-sm text-txt-secondary">{{ formatAmount(register.opening_amount) }}</span>
          <span class="text-sm font-semibold text-txt-primary">{{ formatAmount(register.expected_amount) }}</span>
          <span class="text-sm text-txt-secondary">{{ formatAmount(register.closing_amount) }}</span>
          <span>
            <StatusBadge :label="register.status_label" :tone="register.status === 'open' ? 'success' : 'neutral'" />
          </span>
          <span class="truncate text-sm text-txt-secondary">{{ register.opened_by_name ?? '—' }}</span>
          <div class="flex justify-end">
            <IconButton :icon="Eye" label="Ver detalhes" @click="openDetail(register)" />
          </div>
        </div>
      </div>
    </div>

    <!-- OPEN NEW CAIXA VIEW -->
    <div v-else-if="view === 'open'" class="overflow-hidden rounded-2xl border border-border bg-surface-raised shadow-card">
      <div class="flex items-center gap-3.5 border-b border-border px-6 py-5">
        <IconButton :icon="ArrowLeft" label="Voltar" @click="view = 'list'" />
        <div>
          <div class="font-display text-lg font-bold text-txt-primary">Abrir novo caixa</div>
          <span class="text-sm text-txt-secondary">Abertura feita na própria tela, sem modal.</span>
        </div>
      </div>

      <form class="space-y-4 px-6 py-6" @submit.prevent="handleOpen">
        <div class="max-w-xs">
          <BaseInput :model-value="openAmount" label="Valor de abertura" :error="firstFieldError(openError, 'opening_amount')" @update:model-value="(v) => (openAmount = maskCurrency(v))" />
        </div>
        <BaseTextarea v-model="openNotes" label="Observação" :error="firstFieldError(openError, 'notes')" />
        <p v-if="openError" class="text-sm text-rose-600">{{ parse(openError).message }}</p>
      </form>

      <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
        <BaseButton type="button" variant="ghost" :block="false" @click="view = 'list'">Voltar</BaseButton>
        <BaseButton type="button" :loading="openSaving" :block="false" @click="handleOpen">Abrir caixa</BaseButton>
      </div>
    </div>

    <!-- DETAIL VIEW -->
    <div v-else-if="view === 'detail' && selected" class="space-y-4">
      <div class="overflow-hidden rounded-2xl border border-border bg-surface-raised shadow-card">
        <div class="flex items-center gap-3.5 border-b border-border px-6 py-5">
          <IconButton :icon="ArrowLeft" label="Voltar" @click="backToList" />
          <div>
            <div class="font-display text-lg font-bold text-txt-primary">Caixa #{{ selected.id }}</div>
            <span class="text-sm text-txt-secondary">Detalhes, operações e fechamento do caixa.</span>
          </div>
        </div>

        <div class="space-y-4 px-6 py-6">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <BaseInput :model-value="formatDateTime(selected.opened_at)" label="Data de abertura" disabled />
            <BaseInput :model-value="formatAmount(selected.expected_amount)" label="Fechamento sugerido" disabled />
            <BaseInput
              :model-value="detailOpeningAmount"
              label="Valor abertura"
              :disabled="!canOperate || selected.status === 'closed'"
              @update:model-value="(v) => (detailOpeningAmount = maskCurrency(v))"
            />
            <BaseInput :model-value="selected.status_label" label="Status" disabled />
            <BaseInput
              :model-value="detailClosingAmount"
              label="Valor fechamento"
              :disabled="!canOperate || selected.status === 'closed'"
              @update:model-value="(v) => (detailClosingAmount = maskCurrency(v))"
            />
          </div>

          <BaseTextarea v-model="detailNotes" label="Observação" :disabled="!canOperate || selected.status === 'closed'" />

          <p v-if="saveError" class="text-sm text-rose-600">{{ parse(saveError).message }}</p>
          <p v-if="closeError" class="text-sm text-rose-600">{{ parse(closeError).message }}</p>

          <div v-if="canOperate && selected.status === 'open'" class="flex flex-wrap items-center gap-3">
            <BaseButton type="button" :loading="saveSaving" :block="false" @click="handleSave">Salvar alterações</BaseButton>
            <BaseButton type="button" variant="danger" :loading="closeSaving" :block="false" @click="handleClose">Fechar caixa</BaseButton>
            <span class="rounded-full border border-border px-4 py-2 text-xs text-txt-secondary">Entradas: <strong class="text-txt-primary">{{ formatAmount(kpiEntradas) }}</strong></span>
            <span class="rounded-full border border-border px-4 py-2 text-xs text-txt-secondary">Saídas: <strong class="text-txt-primary">{{ formatAmount(kpiSaidas) }}</strong></span>
            <span class="rounded-full bg-emerald-50 px-4 py-2 text-xs text-emerald-700">Total: <strong>{{ formatAmount(selected.expected_amount) }}</strong></span>
          </div>
        </div>
      </div>

      <!-- Nova operação -->
      <div v-if="canOperate && selected.status === 'open'" class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <div class="mb-4">
          <div class="font-display text-base font-bold text-txt-primary">Nova operação</div>
          <span class="text-sm text-txt-secondary">Lançamentos manuais de entrada (reforço) e saída (sangria).</span>
        </div>
        <form class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-5 lg:items-end" @submit.prevent="handleAddOperation">
          <BaseInput :model-value="novaOpValor" label="Valor" :error="firstFieldError(operationError, 'amount')" @update:model-value="(v) => (novaOpValor = maskCurrency(v))" />
          <BaseSelect v-model="novaOpPaymentMethodId" label="Forma pagamento" :options="paymentMethodOptions" :error="firstFieldError(operationError, 'payment_method_id')" />
          <BaseSelect v-model="novaOpTipo" label="Tipo" :options="[{ value: 'Entrada', label: 'Entrada' }, { value: 'Saída', label: 'Saída' }]" />
          <BaseInput v-model="novaOpNotes" label="Observação" placeholder="Ex.: sangria, reforço" :error="firstFieldError(operationError, 'notes')" />
          <BaseButton type="submit" :loading="operationSaving" :block="false">Adicionar</BaseButton>
        </form>
        <p v-if="operationError && !firstFieldError(operationError, 'amount')" class="mt-2 text-sm text-rose-600">{{ parse(operationError).message }}</p>
      </div>

      <!-- Operações table -->
      <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
        <div class="px-6 pt-5">
          <div class="font-display text-base font-bold text-txt-primary">Operações do caixa</div>
        </div>
        <div class="mt-3 grid grid-cols-[0.9fr_0.9fr_1fr_1fr_2fr_76px] items-center gap-2 border-b border-border px-6 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
          <span>Hora</span>
          <span>Tipo</span>
          <span>Forma</span>
          <span>Valor</span>
          <span>Observação</span>
          <span class="text-right">Ação</span>
        </div>

        <div v-if="store.operations.length === 0" class="px-6 py-9 text-center text-sm text-txt-muted">
          Nenhuma operação registrada neste caixa.
        </div>
        <div
          v-for="operation in store.operations"
          v-else
          :key="operation.id"
          class="grid grid-cols-[0.9fr_0.9fr_1fr_1fr_2fr_76px] items-center gap-2 border-b border-border px-6 py-3 last:border-0"
        >
          <span class="text-sm text-txt-secondary">{{ new Date(operation.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }}</span>
          <span>
            <StatusBadge :label="operation.type_label" :tone="operation.type === 'in' ? 'info' : 'danger'" />
          </span>
          <span class="text-sm text-txt-secondary">{{ operation.payment_method_name ?? '—' }}</span>
          <span class="text-sm font-semibold text-txt-primary">{{ formatAmount(operation.amount) }}</span>
          <span class="flex min-w-0 items-center gap-1.5 truncate text-sm text-txt-secondary">
            <span class="truncate">{{ operation.notes ?? operation.origin_label }}</span>
            <StatusBadge v-if="operation.sale_status === 'canceled'" label="Venda cancelada" tone="danger" />
          </span>
          <div class="flex justify-end gap-1">
            <IconButton v-if="operation.origin === 'sale' && operation.reference_id" :icon="ShoppingCart" label="Ver itens da venda" @click="viewSaleItems(operation.reference_id)" />
            <IconButton
              v-if="canOperate && selected.status === 'open' && operation.origin !== 'sale' && operation.origin !== 'adjustment'"
              :icon="Trash2"
              label="Remover"
              tone="danger"
              @click="handleRemoveOperation(operation.id)"
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-5 border-t border-border px-6 py-3.5">
          <span class="text-xs text-txt-secondary">Entradas: <strong class="text-txt-primary">{{ formatAmount(kpiEntradas) }}</strong></span>
          <span class="text-xs text-txt-secondary">Saídas: <strong class="text-txt-primary">{{ formatAmount(kpiSaidas) }}</strong></span>
          <span class="text-xs text-txt-secondary">Total: <strong class="text-emerald-700">{{ formatAmount(selected.expected_amount) }}</strong></span>
        </div>
      </div>
    </div>

    <!-- MODAL: ITENS DA VENDA -->
    <BaseModal
      :open="showSaleDetail"
      :title="saleDetail ? `Venda ${saleDetail.number}` : 'Venda'"
      subtitle="Itens vendidos, descontos e forma de pagamento."
      @close="showSaleDetail = false"
    >
      <div v-if="saleDetailLoading" class="py-8 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="saleDetail" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 text-sm">
          <span class="text-txt-secondary">Cliente: <strong class="text-txt-primary">{{ saleDetail.customer_name ?? 'Não informado' }}</strong></span>
          <span class="text-txt-secondary">Vendedor: <strong class="text-txt-primary">{{ saleDetail.seller_name ?? '—' }}</strong></span>
          <span class="text-txt-secondary">Forma de pagamento: <strong class="text-txt-primary">{{ saleDetail.payment_method_name ?? '—' }}</strong></span>
        </div>

        <div class="overflow-hidden rounded-xl border border-border">
          <div class="grid grid-cols-[2fr_0.6fr_1fr_1fr] gap-2 border-b border-border bg-surface-subtle px-4 py-2 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
            <span>Produto</span>
            <span class="text-center">Qtd.</span>
            <span class="text-right">Unitário</span>
            <span class="text-right">Total</span>
          </div>
          <div v-for="item in saleDetail.items" :key="item.id" class="grid grid-cols-[2fr_0.6fr_1fr_1fr] gap-2 border-b border-border px-4 py-2.5 text-sm last:border-0">
            <div class="min-w-0">
              <p class="truncate font-semibold text-txt-primary">{{ item.product_name ?? '—' }}</p>
              <p class="text-[11px] text-txt-muted">Cód. {{ item.product_code ?? '—' }}</p>
            </div>
            <span class="text-center text-txt-secondary">{{ item.quantity }}</span>
            <span class="text-right text-txt-secondary">{{ formatAmount(item.unit_price) }}</span>
            <span class="text-right font-semibold text-txt-primary">{{ formatAmount(item.total) }}</span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-5 text-sm">
          <span class="text-txt-secondary">Subtotal: <strong class="text-txt-primary">{{ formatAmount(saleDetail.subtotal) }}</strong></span>
          <span class="text-txt-secondary">Desconto: <strong class="text-txt-primary">{{ formatAmount(saleDetail.discount) }}</strong></span>
          <span class="text-txt-secondary">Total: <strong class="text-emerald-700">{{ formatAmount(saleDetail.total) }}</strong></span>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
