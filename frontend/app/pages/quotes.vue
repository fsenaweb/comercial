<script setup lang="ts">
import { Ban, CheckCircle2, Eye, FileText, Search, TrendingUp } from 'lucide-vue-next'

interface UserOption {
  id: number
  name: string
}

interface PaymentMethod {
  id: number
  name: string
  active_on_pos: boolean
}

interface QuoteListItem {
  id: number
  number: string
  customer_name: string | null
  seller_id: number
  seller_name: string | null
  total: string
  status: string
  status_label: string
  expires_at: string | null
  converted_to_sale_number: string | null
  created_at: string
}

interface QuoteItemDetail {
  id: number
  product_name: string | null
  product_code: string | null
  quantity: number
  unit_price: string
  discount: string
  total: string
}

interface QuoteDetail extends QuoteListItem {
  subtotal: string
  discount: string
  canceled_reason: string | null
  canceled_at: string | null
  items: QuoteItemDetail[]
}

const api = useApi()
const auth = useAuthStore()
const cashRegisterStore = useCashRegisterStore()
const { parse, firstFieldError } = useApiError()
const { format } = useCurrencyMask()

const quotes = ref<QuoteListItem[]>([])
const sellers = ref<UserOption[]>([])
const paymentMethods = ref<PaymentMethod[]>([])
const loading = ref(true)

const cashRegisterOpen = computed(() => cashRegisterStore.current !== null)

const search = ref('')
const sellerId = ref<string | number>('')
const dateFrom = ref('')
const dateTo = ref('')
const statusFilter = ref('')

const sellerOptions = computed(() => [{ value: '', label: 'Todos os vendedores' }, ...sellers.value.map((s) => ({ value: s.id, label: s.name }))])
const paymentMethodOptions = computed(() => paymentMethods.value.filter((p) => p.active_on_pos).map((p) => ({ value: p.id, label: p.name })))
const statusOptions = [
  { value: '', label: 'Todos' },
  { value: 'pending', label: 'Pendentes' },
  { value: 'converted', label: 'Convertidos' },
  { value: 'canceled', label: 'Cancelados' },
]

const canManageQuote = computed(() => auth.isAdmin || auth.isCashier)

function formatAmount(value: string | number): string {
  return format(Math.round(Number(value) * 100))
}

// A API serializa `expires_at` como datetime ISO completo (cast `date` do
// Laravel ainda inclui a meia-noite no fuso da app) — extrai só a parte da
// data antes de remontar, senão concatenar T00:00:00 gera uma string inválida.
function toDateOnly(value: string): string {
  return value.slice(0, 10)
}

function formatDate(value: string): string {
  return new Date(`${toDateOnly(value)}T00:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function isExpired(quote: QuoteListItem): boolean {
  if (!quote.expires_at) return false
  return new Date(`${toDateOnly(quote.expires_at)}T23:59:59`) < new Date()
}

function buildQuery() {
  const query = new URLSearchParams()
  query.set('is_quote', '1')
  if (search.value.trim()) query.set('search', search.value.trim())
  if (sellerId.value) query.set('seller_id', String(sellerId.value))
  if (dateFrom.value) query.set('date_from', dateFrom.value)
  if (dateTo.value) query.set('date_to', dateTo.value)
  if (statusFilter.value) query.set('status', statusFilter.value)
  return query.toString()
}

async function load() {
  loading.value = true
  const qs = buildQuery()
  const { data } = await api<{ data: QuoteListItem[] }>(`/sales?${qs}`)
  quotes.value = data
  loading.value = false
}

async function loadSellers() {
  const { data } = await api<{ data: UserOption[] }>('/users/active')
  sellers.value = data
}

async function loadPaymentMethods() {
  const { data } = await api<{ data: PaymentMethod[] }>('/payment-methods')
  paymentMethods.value = data
}

const pendingQuotes = computed(() => quotes.value.filter((q) => q.status === 'pending'))
const openTotal = computed(() => pendingQuotes.value.reduce((sum, q) => sum + Number(q.total), 0))
const convertedCount = computed(() => quotes.value.filter((q) => q.status === 'converted').length)

// ---- Detalhe do orçamento ----
const showDetail = ref(false)
const detailLoading = ref(false)
const detail = ref<QuoteDetail | null>(null)

async function viewQuote(quoteId: number) {
  showDetail.value = true
  detailLoading.value = true
  detail.value = null
  const { data } = await api<{ data: QuoteDetail }>(`/sales/${quoteId}`)
  detail.value = data
  detailLoading.value = false
}

// ---- Converter em venda ----
const showConvertModal = ref(false)
const convertQuoteId = ref<number | null>(null)
const convertPaymentMethodId = ref<number | null>(null)
const convertSaving = ref(false)
const convertError = ref<unknown>(null)

function openConvertModal(quoteId: number) {
  convertQuoteId.value = quoteId
  convertPaymentMethodId.value = null
  convertError.value = null
  showConvertModal.value = true
}

async function confirmConvert() {
  if (!convertQuoteId.value || !convertPaymentMethodId.value) return
  convertSaving.value = true
  convertError.value = null
  try {
    const { data } = await api<{ data: { id: number } }>(`/sales/${convertQuoteId.value}/convert`, {
      method: 'POST',
      body: { payment_method_id: convertPaymentMethodId.value },
    })
    showConvertModal.value = false
    window.open(`/sales/${data.id}/receipt`, '_blank')
    await load()
  } catch (err) {
    convertError.value = err
  } finally {
    convertSaving.value = false
  }
}

// ---- Cancelamento de orçamento ----
const showCancelModal = ref(false)
const cancelQuoteId = ref<number | null>(null)
const cancelReason = ref('')
const cancelSaving = ref(false)
const cancelError = ref<unknown>(null)

function openCancelModal(quoteId: number) {
  cancelQuoteId.value = quoteId
  cancelReason.value = ''
  cancelError.value = null
  showCancelModal.value = true
}

async function confirmCancelQuote() {
  if (!cancelQuoteId.value) return
  cancelSaving.value = true
  cancelError.value = null
  try {
    await api(`/sales/${cancelQuoteId.value}/cancel`, { method: 'POST', body: { reason: cancelReason.value } })
    showCancelModal.value = false
    await load()
  } catch (err) {
    cancelError.value = err
  } finally {
    cancelSaving.value = false
  }
}

await Promise.all([load(), loadSellers(), loadPaymentMethods(), cashRegisterStore.fetchCurrent()])
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Orçamentos</h1>
      <p class="text-sm text-txt-secondary">Cotações montadas no PDV, sem baixa de estoque nem lançamento no caixa até serem convertidas em venda.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <StatCard label="Orçamentos no filtro" :value="quotes.length" :icon="FileText" tone="violet" />
      <StatCard label="Valor em aberto" :value="formatAmount(openTotal)" :icon="TrendingUp" tone="amber" />
      <StatCard label="Convertidos" :value="convertedCount" :icon="CheckCircle2" tone="emerald" />
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card">
      <div class="min-w-[220px] flex-1">
        <label class="mb-1 block text-sm font-medium text-txt-secondary">Buscar</label>
        <label class="flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-txt-muted">
          <Search :size="15" />
          <input v-model="search" type="text" placeholder="Número do orçamento ou cliente..." class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none" @keyup.enter="load">
        </label>
      </div>
      <div class="w-56">
        <BaseSelect v-model="sellerId" label="Vendedor" :options="sellerOptions" />
      </div>
      <div class="w-44">
        <BaseSelect v-model="statusFilter" label="Status" :options="statusOptions" />
      </div>
      <div class="w-44">
        <BaseInput v-model="dateFrom" type="date" label="De" />
      </div>
      <div class="w-44">
        <BaseInput v-model="dateTo" type="date" label="Até" />
      </div>
      <BaseButton :block="false" @click="load">Filtrar</BaseButton>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
      <div class="grid grid-cols-[0.9fr_1.3fr_1.3fr_1fr_1fr_1fr_1fr_120px] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
        <span>Nº</span>
        <span>Data</span>
        <span>Cliente</span>
        <span>Vendedor</span>
        <span class="text-right">Total</span>
        <span>Validade</span>
        <span>Status</span>
        <span class="text-right">Ações</span>
      </div>

      <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="quotes.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">Nenhum orçamento encontrado para o filtro selecionado.</div>
      <div
        v-for="quote in quotes"
        v-else
        :key="quote.id"
        class="grid grid-cols-[0.9fr_1.3fr_1.3fr_1fr_1fr_1fr_1fr_120px] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
      >
        <span class="text-sm font-bold text-emerald-700">{{ quote.number }}</span>
        <span class="text-sm text-txt-secondary">{{ formatDateTime(quote.created_at) }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ quote.customer_name ?? 'Não informado' }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ quote.seller_name ?? '—' }}</span>
        <span class="text-right text-sm font-semibold text-txt-primary">{{ formatAmount(quote.total) }}</span>
        <span class="text-sm" :class="quote.status === 'pending' && isExpired(quote) ? 'font-semibold text-rose-600' : 'text-txt-secondary'">
          {{ quote.expires_at ? formatDate(quote.expires_at) : '—' }}
        </span>
        <span>
          <StatusBadge
            :label="quote.status === 'pending' && isExpired(quote) ? 'Vencido' : quote.status_label"
            :tone="quote.status === 'converted' ? 'success' : quote.status === 'canceled' ? 'danger' : (isExpired(quote) ? 'danger' : 'info')"
          />
        </span>
        <div class="flex justify-end gap-1">
          <IconButton :icon="Eye" label="Ver detalhes" @click="viewQuote(quote.id)" />
          <IconButton
            v-if="canManageQuote && quote.status === 'pending' && !isExpired(quote)"
            :icon="CheckCircle2"
            :label="cashRegisterOpen ? 'Converter em venda' : 'Abra o caixa para converter em venda'"
            :disabled="!cashRegisterOpen"
            @click="openConvertModal(quote.id)"
          />
          <IconButton
            v-if="canManageQuote && quote.status === 'pending'"
            :icon="Ban"
            label="Cancelar orçamento"
            tone="danger"
            @click="openCancelModal(quote.id)"
          />
        </div>
      </div>
    </div>

    <BaseModal :open="showDetail" size="xl" :title="detail ? `Orçamento ${detail.number}` : 'Orçamento'" subtitle="Itens, descontos e status." @close="showDetail = false">
      <div v-if="detailLoading" class="py-8 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="detail" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 text-sm">
          <span class="text-txt-secondary">Cliente: <strong class="text-txt-primary">{{ detail.customer_name ?? 'Não informado' }}</strong></span>
          <span class="text-txt-secondary">Vendedor: <strong class="text-txt-primary">{{ detail.seller_name ?? '—' }}</strong></span>
          <span class="text-txt-secondary">Validade: <strong class="text-txt-primary">{{ detail.expires_at ? formatDate(detail.expires_at) : 'Sem validade definida' }}</strong></span>
          <span class="text-txt-secondary">Data: <strong class="text-txt-primary">{{ formatDateTime(detail.created_at) }}</strong></span>
        </div>

        <div v-if="detail.status === 'converted'" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
          <p class="font-bold">Orçamento convertido em venda{{ detail.converted_to_sale_number ? ` ${detail.converted_to_sale_number}` : '' }}</p>
        </div>
        <div v-if="detail.status === 'canceled'" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
          <p class="font-bold">Orçamento cancelado{{ detail.canceled_at ? ` em ${formatDateTime(detail.canceled_at)}` : '' }}</p>
          <p v-if="detail.canceled_reason" class="mt-0.5">Motivo: {{ detail.canceled_reason }}</p>
        </div>

        <div class="overflow-hidden rounded-xl border border-border">
          <div class="grid grid-cols-[2fr_0.6fr_1fr_1fr] gap-2 border-b border-border bg-surface-subtle px-4 py-2 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
            <span>Produto</span>
            <span class="text-center">Qtd.</span>
            <span class="text-right">Unitário</span>
            <span class="text-right">Total</span>
          </div>
          <div v-for="item in detail.items" :key="item.id" class="grid grid-cols-[2fr_0.6fr_1fr_1fr] gap-2 border-b border-border px-4 py-2.5 text-sm last:border-0">
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
          <span class="text-txt-secondary">Subtotal: <strong class="text-txt-primary">{{ formatAmount(detail.subtotal) }}</strong></span>
          <span class="text-txt-secondary">Desconto: <strong class="text-txt-primary">{{ formatAmount(detail.discount) }}</strong></span>
          <span class="text-txt-secondary">Total: <strong class="text-emerald-700">{{ formatAmount(detail.total) }}</strong></span>
        </div>
      </div>
    </BaseModal>

    <BaseModal :open="showConvertModal" title="Converter em venda" subtitle="Escolha a forma de pagamento — o orçamento vira uma venda de verdade, com baixa de estoque e lançamento no caixa." @close="showConvertModal = false">
      <div class="space-y-4">
        <p v-if="!cashRegisterOpen" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
          Nenhum caixa aberto — não é possível converter em venda agora. Abra o caixa e tente novamente.
        </p>
        <BaseSelect v-model="convertPaymentMethodId" label="Forma de pagamento" :options="paymentMethodOptions" :disabled="!cashRegisterOpen" :error="firstFieldError(convertError, 'payment_method_id')" />
        <p v-if="convertError && !firstFieldError(convertError, 'payment_method_id')" class="text-sm text-rose-600">{{ parse(convertError).message }}</p>
        <div class="flex justify-end gap-3 border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="showConvertModal = false">Voltar</BaseButton>
          <BaseButton type="button" :loading="convertSaving" :disabled="!cashRegisterOpen || !convertPaymentMethodId" :block="false" @click="confirmConvert">Converter em venda</BaseButton>
        </div>
      </div>
    </BaseModal>

    <BaseModal :open="showCancelModal" title="Cancelar orçamento" subtitle="O orçamento fica marcado como cancelado — como nunca baixou estoque nem caixa, nada precisa ser estornado." @close="showCancelModal = false">
      <div class="space-y-4">
        <BaseInput v-model="cancelReason" label="Motivo do cancelamento" placeholder="Ex.: cliente recusou, orçamento vencido..." :error="firstFieldError(cancelError, 'reason')" />
        <p v-if="cancelError && !firstFieldError(cancelError, 'reason')" class="text-sm text-rose-600">{{ parse(cancelError).message }}</p>
        <div class="flex justify-end gap-3 border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="showCancelModal = false">Voltar</BaseButton>
          <BaseButton type="button" variant="danger" :loading="cancelSaving" :block="false" @click="confirmCancelQuote">Confirmar cancelamento</BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
