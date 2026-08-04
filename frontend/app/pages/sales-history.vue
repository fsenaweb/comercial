<script setup lang="ts">
import { Ban, Eye, Printer, Receipt, Search, TrendingUp, Users } from 'lucide-vue-next'

interface UserOption {
  id: number
  name: string
}

interface SaleListItem {
  id: number
  number: string
  customer_name: string | null
  seller_id: number
  seller_name: string | null
  payment_method_name: string | null
  total: string
  status: string
  status_label: string
  created_at: string
}

interface SaleItemDetail {
  id: number
  product_name: string | null
  product_code: string | null
  quantity: string
  unit_price: string
  discount: string
  total: string
}

interface SalePaymentDetail {
  id: number
  payment_method_id: number
  payment_method_name: string | null
  amount: string
}

interface SaleDetail extends SaleListItem {
  subtotal: string
  discount: string
  canceled_reason: string | null
  canceled_at: string | null
  payments: SalePaymentDetail[]
  items: SaleItemDetail[]
}

const api = useApi()
const auth = useAuthStore()
const { parse, firstFieldError } = useApiError()
const { format } = useCurrencyMask()
const { formatQuantity } = useQuantityFormat()
const { printFormatDialog } = usePrintFormatDialog()

const sales = ref<SaleListItem[]>([])
const sellers = ref<UserOption[]>([])
const loading = ref(true)

const search = ref('')
const sellerId = ref<string | number>('')
const dateFrom = ref('')
const dateTo = ref('')
const statusFilter = ref('')
const page = ref(1)
const totalPages = ref(1)
const totalSales = ref(0)
const totalPeriod = ref(0)
const averageTicket = ref(0)

const sellerOptions = computed(() => [{ value: '', label: 'Todos os vendedores' }, ...sellers.value.map((s) => ({ value: s.id, label: s.name }))])
const statusOptions = [
  { value: '', label: 'Todas' },
  { value: 'completed', label: 'Concluídas' },
  { value: 'canceled', label: 'Canceladas' },
]

const canCancelSale = computed(() => auth.isAdmin || auth.isCashier)

function formatAmount(value: string | number): string {
  return format(Math.round(Number(value) * 100))
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function buildQuery() {
  const query = new URLSearchParams()
  query.set('is_quote', '0')
  query.set('page', String(page.value))
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
  const { data, meta, filter_summary: filterSummary } = await api<{
    data: SaleListItem[]
    meta: { current_page: number, last_page: number, total: number }
    filter_summary: { total_amount: number, average_ticket: number }
  }>(`/sales?${qs}`)
  sales.value = data
  totalPages.value = meta.last_page
  totalSales.value = meta.total
  totalPeriod.value = filterSummary.total_amount
  averageTicket.value = filterSummary.average_ticket
  loading.value = false
}

// Sem filtro de data, a listagem já vem só do dia atual por padrão (ver
// SaleController::index) - aplicar um filtro novo sempre volta pra página 1,
// senão o usuário pode ficar "preso" numa página que não existe mais pro
// filtro escolhido.
function applyFilters() {
  page.value = 1
  load()
}

watch(page, () => load())

async function loadSellers() {
  const { data } = await api<{ data: UserOption[] }>('/users/active')
  sellers.value = data
}

// Sem filtro de data explícito a listagem já é só o dia atual (ver
// SaleController::index) - o label reflete isso; com filtro de data, vira
// "Vendas no filtro" pra não afirmar que é "do dia" quando não é.
const salesCountLabel = computed(() => (dateFrom.value || dateTo.value ? 'Vendas no filtro' : 'Vendas do dia'))

// ---- Detalhe da venda ----
const showDetail = ref(false)
const detailLoading = ref(false)
const detail = ref<SaleDetail | null>(null)

async function viewSale(saleId: number) {
  showDetail.value = true
  detailLoading.value = true
  detail.value = null
  const { data } = await api<{ data: SaleDetail }>(`/sales/${saleId}`)
  detail.value = data
  detailLoading.value = false
}

async function printSale(saleId: number) {
  showDetail.value = false
  const format = await printFormatDialog()
  if (format) window.open(`/sales/${saleId}/receipt?format=${format}`, '_blank')
}

// ---- Cancelamento de venda ----
const showCancelModal = ref(false)
const cancelSaleId = ref<number | null>(null)
const cancelReason = ref('')
const cancelSaving = ref(false)
const cancelError = ref<unknown>(null)

function openCancelModal(saleId: number) {
  cancelSaleId.value = saleId
  cancelReason.value = ''
  cancelError.value = null
  showCancelModal.value = true
}

async function confirmCancelSale() {
  if (!cancelSaleId.value) return
  cancelSaving.value = true
  cancelError.value = null
  try {
    await api(`/sales/${cancelSaleId.value}/cancel`, { method: 'POST', body: { reason: cancelReason.value } })
    showCancelModal.value = false
    await load()
  } catch (err) {
    cancelError.value = err
  } finally {
    cancelSaving.value = false
  }
}

await Promise.all([load(), loadSellers()])
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Vendas</h1>
      <p class="text-sm text-txt-secondary">Histórico de vendas realizadas, com filtros por vendedor e período.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <StatCard :label="salesCountLabel" :value="totalSales" :icon="Receipt" tone="violet" />
      <StatCard label="Total do período" :value="formatAmount(totalPeriod)" :icon="TrendingUp" tone="emerald" />
      <StatCard label="Ticket médio" :value="formatAmount(averageTicket)" :icon="Users" tone="sky" />
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card">
      <div class="min-w-[220px] flex-1">
        <label class="mb-1 block text-sm font-medium text-txt-secondary">Buscar</label>
        <label class="flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-txt-muted">
          <Search :size="15" />
          <input v-model="search" type="text" placeholder="Número da venda ou cliente..." class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none" @keyup.enter="applyFilters">
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
      <BaseButton :block="false" @click="applyFilters">Filtrar</BaseButton>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
      <div class="grid grid-cols-[0.9fr_1.3fr_1.3fr_1fr_1fr_0.9fr_90px] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
        <span>Nº</span>
        <span>Data</span>
        <span>Cliente</span>
        <span>Vendedor</span>
        <span class="text-right">Total</span>
        <span>Status</span>
        <span class="text-right">Ações</span>
      </div>

      <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="sales.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">Nenhuma venda encontrada para o filtro selecionado.</div>
      <div
        v-for="sale in sales"
        v-else
        :key="sale.id"
        class="grid grid-cols-[0.9fr_1.3fr_1.3fr_1fr_1fr_0.9fr_90px] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
      >
        <span class="text-sm font-bold text-emerald-700">{{ sale.number }}</span>
        <span class="text-sm text-txt-secondary">{{ formatDateTime(sale.created_at) }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ sale.customer_name ?? 'Não informado' }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ sale.seller_name ?? '-' }}</span>
        <span class="text-right text-sm font-semibold text-txt-primary">{{ formatAmount(sale.total) }}</span>
        <span>
          <StatusBadge :label="sale.status_label" :tone="sale.status === 'canceled' ? 'danger' : 'success'" />
        </span>
        <div class="flex justify-end gap-1">
          <IconButton :icon="Eye" label="Ver detalhes" @click="viewSale(sale.id)" />
          <IconButton :icon="Printer" label="Imprimir" @click="printSale(sale.id)" />
          <IconButton
            v-if="canCancelSale && sale.status === 'completed'"
            :icon="Ban"
            label="Cancelar venda"
            tone="danger"
            @click="openCancelModal(sale.id)"
          />
        </div>
      </div>

      <div v-if="!loading && sales.length > 0" class="flex items-center justify-end gap-3 border-t border-border px-5 py-3.5">
        <span class="text-xs text-txt-secondary">
          Página <strong class="text-txt-primary">{{ page }}</strong> de
          <strong class="text-txt-primary">{{ totalPages }}</strong>
          ({{ totalSales }} venda{{ totalSales === 1 ? '' : 's' }})
        </span>
        <BaseButton variant="ghost" :block="false" :disabled="page <= 1" @click="page = page - 1">Anterior</BaseButton>
        <BaseButton variant="ghost" :block="false" :disabled="page >= totalPages" @click="page = page + 1">Próxima</BaseButton>
      </div>
    </div>

    <BaseModal :open="showDetail" size="xl" :title="detail ? `Venda ${detail.number}` : 'Venda'" subtitle="Itens vendidos, descontos e forma de pagamento." @close="showDetail = false">
      <div v-if="detailLoading" class="py-8 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="detail" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 text-sm">
          <span class="text-txt-secondary">Cliente: <strong class="text-txt-primary">{{ detail.customer_name ?? 'Não informado' }}</strong></span>
          <span class="text-txt-secondary">Vendedor: <strong class="text-txt-primary">{{ detail.seller_name ?? '-' }}</strong></span>
          <span class="text-txt-secondary">Data: <strong class="text-txt-primary">{{ formatDateTime(detail.created_at) }}</strong></span>
          <span class="text-txt-secondary">
            Forma de pagamento:
            <strong v-if="detail.payments.length === 0" class="text-txt-primary">-</strong>
            <strong v-for="(payment, index) in detail.payments" :key="payment.id" class="text-txt-primary">
              {{ payment.payment_method_name ?? '-' }} ({{ formatAmount(payment.amount) }})<span v-if="index < detail.payments.length - 1">, </span>
            </strong>
          </span>
        </div>

        <div v-if="detail.status === 'canceled'" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
          <p class="font-bold">Venda cancelada{{ detail.canceled_at ? ` em ${formatDateTime(detail.canceled_at)}` : '' }}</p>
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
              <p class="truncate font-semibold text-txt-primary">{{ item.product_name ?? '-' }}</p>
              <p class="text-[11px] text-txt-muted">Cód. {{ item.product_code ?? '-' }}</p>
            </div>
            <span class="text-center text-txt-secondary">{{ formatQuantity(item.quantity) }}</span>
            <span class="text-right text-txt-secondary">{{ formatAmount(item.unit_price) }}</span>
            <span class="text-right font-semibold text-txt-primary">{{ formatAmount(item.total) }}</span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-5 text-sm">
          <span class="text-txt-secondary">Subtotal: <strong class="text-txt-primary">{{ formatAmount(detail.subtotal) }}</strong></span>
          <span class="text-txt-secondary">
            {{ Number(detail.discount) < 0 ? 'Acréscimo' : 'Desconto' }}:
            <strong class="text-txt-primary">{{ formatAmount(Math.abs(Number(detail.discount))) }}</strong>
          </span>
          <span class="text-txt-secondary">Total: <strong class="text-emerald-700">{{ formatAmount(detail.total) }}</strong></span>
        </div>

        <div class="flex justify-end border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="printSale(detail.id)">
            <Printer :size="15" class="mr-1.5 inline" /> Imprimir
          </BaseButton>
        </div>
      </div>
    </BaseModal>

    <BaseModal :open="showCancelModal" title="Cancelar venda" subtitle="A venda fica marcada como cancelada, com estorno automático de estoque e caixa - nada é apagado." @close="showCancelModal = false">
      <div class="space-y-4">
        <BaseInput v-model="cancelReason" label="Motivo do cancelamento" placeholder="Ex.: cliente desistiu, erro de digitação..." :error="firstFieldError(cancelError, 'reason')" />
        <p v-if="cancelError && !firstFieldError(cancelError, 'reason')" class="text-sm text-rose-600">{{ parse(cancelError).message }}</p>
        <div class="flex justify-end gap-3 border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="showCancelModal = false">Voltar</BaseButton>
          <BaseButton type="button" variant="danger" :loading="cancelSaving" :block="false" @click="confirmCancelSale">Confirmar cancelamento</BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
