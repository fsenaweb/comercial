<script setup lang="ts">
import { Eye, Receipt, Search, TrendingUp, Users } from 'lucide-vue-next'

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
  quantity: number
  unit_price: string
  discount: string
  total: string
}

interface SaleDetail extends SaleListItem {
  subtotal: string
  discount: string
  items: SaleItemDetail[]
}

const api = useApi()
const { format } = useCurrencyMask()

const sales = ref<SaleListItem[]>([])
const sellers = ref<UserOption[]>([])
const loading = ref(true)

const search = ref('')
const sellerId = ref<string | number>('')
const dateFrom = ref('')
const dateTo = ref('')

const sellerOptions = computed(() => [{ value: '', label: 'Todos os vendedores' }, ...sellers.value.map((s) => ({ value: s.id, label: s.name }))])

function formatAmount(value: string | number): string {
  return format(Math.round(Number(value) * 100))
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function buildQuery() {
  const query = new URLSearchParams()
  if (search.value.trim()) query.set('search', search.value.trim())
  if (sellerId.value) query.set('seller_id', String(sellerId.value))
  if (dateFrom.value) query.set('date_from', dateFrom.value)
  if (dateTo.value) query.set('date_to', dateTo.value)
  return query.toString()
}

async function load() {
  loading.value = true
  const qs = buildQuery()
  const { data } = await api<{ data: SaleListItem[] }>(`/sales${qs ? `?${qs}` : ''}`)
  sales.value = data
  loading.value = false
}

async function loadSellers() {
  const { data } = await api<{ data: UserOption[] }>('/users/active')
  sellers.value = data
}

const totalPeriod = computed(() => sales.value.reduce((sum, s) => sum + Number(s.total), 0))
const averageTicket = computed(() => (sales.value.length > 0 ? totalPeriod.value / sales.value.length : 0))

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

await Promise.all([load(), loadSellers()])
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Vendas</h1>
      <p class="text-sm text-txt-secondary">Histórico de vendas realizadas, com filtros por vendedor e período.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <StatCard label="Vendas no filtro" :value="sales.length" :icon="Receipt" tone="violet" />
      <StatCard label="Total do período" :value="formatAmount(totalPeriod)" :icon="TrendingUp" tone="emerald" />
      <StatCard label="Ticket médio" :value="formatAmount(averageTicket)" :icon="Users" tone="sky" />
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card">
      <div class="min-w-[220px] flex-1">
        <label class="mb-1 block text-sm font-medium text-txt-secondary">Buscar</label>
        <label class="flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-txt-muted">
          <Search :size="15" />
          <input v-model="search" type="text" placeholder="Número da venda ou cliente..." class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none" @keyup.enter="load">
        </label>
      </div>
      <div class="w-56">
        <BaseSelect v-model="sellerId" label="Vendedor" :options="sellerOptions" />
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
      <div class="grid grid-cols-[0.9fr_1.3fr_1.3fr_1fr_1fr_0.9fr_60px] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
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
        class="grid grid-cols-[0.9fr_1.3fr_1.3fr_1fr_1fr_0.9fr_60px] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
      >
        <span class="text-sm font-bold text-emerald-700">{{ sale.number }}</span>
        <span class="text-sm text-txt-secondary">{{ formatDateTime(sale.created_at) }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ sale.customer_name ?? 'Não informado' }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ sale.seller_name ?? '—' }}</span>
        <span class="text-right text-sm font-semibold text-txt-primary">{{ formatAmount(sale.total) }}</span>
        <span>
          <StatusBadge :label="sale.status_label" :tone="sale.status === 'completed' ? 'success' : 'neutral'" />
        </span>
        <div class="flex justify-end">
          <IconButton :icon="Eye" label="Ver detalhes" @click="viewSale(sale.id)" />
        </div>
      </div>
    </div>

    <BaseModal :open="showDetail" :title="detail ? `Venda ${detail.number}` : 'Venda'" subtitle="Itens vendidos, descontos e forma de pagamento." @close="showDetail = false">
      <div v-if="detailLoading" class="py-8 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="detail" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 text-sm">
          <span class="text-txt-secondary">Cliente: <strong class="text-txt-primary">{{ detail.customer_name ?? 'Não informado' }}</strong></span>
          <span class="text-txt-secondary">Vendedor: <strong class="text-txt-primary">{{ detail.seller_name ?? '—' }}</strong></span>
          <span class="text-txt-secondary">Forma de pagamento: <strong class="text-txt-primary">{{ detail.payment_method_name ?? '—' }}</strong></span>
          <span class="text-txt-secondary">Data: <strong class="text-txt-primary">{{ formatDateTime(detail.created_at) }}</strong></span>
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
  </div>
</template>
