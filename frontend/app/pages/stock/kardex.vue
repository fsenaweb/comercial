<script setup lang="ts">
import { ArrowDownCircle, ArrowUpCircle, History, RefreshCw, Search, ShoppingCart } from 'lucide-vue-next'

interface StockMovement {
  id: number
  product_variation_id: number
  product_name: string | null
  product_code: string | null
  type: 'in' | 'out' | 'adjustment' | 'sale'
  type_label: string
  quantity: number
  origin: string
  reference_id: number | null
  user_id: number
  user_name: string | null
  created_at: string
}

const api = useApi()

const movements = ref<StockMovement[]>([])
const loading = ref(true)
const loadingMore = ref(false)
const hasMore = ref(false)
const page = ref(1)

const search = ref('')
const typeFilter = ref<string>('')
const dateFrom = ref('')
const dateTo = ref('')

const showOriginModal = ref(false)
const originModalMovement = ref<StockMovement | null>(null)

function openOriginModal(movement: StockMovement) {
  originModalMovement.value = movement
  showOriginModal.value = true
}

const typeOptions = [
  { value: '', label: 'Todos os tipos' },
  { value: 'in', label: 'Entrada' },
  { value: 'out', label: 'Saída' },
  { value: 'adjustment', label: 'Ajuste' },
  { value: 'sale', label: 'Venda' },
]

const typeTone: Record<string, 'success' | 'danger' | 'info' | 'warning'> = {
  in: 'success',
  out: 'danger',
  adjustment: 'warning',
  sale: 'info',
}

function buildQuery(targetPage: number) {
  const query = new URLSearchParams()
  query.set('page', String(targetPage))
  if (search.value.trim()) query.set('search', search.value.trim())
  if (typeFilter.value) query.set('type', typeFilter.value)
  if (dateFrom.value) query.set('date_from', dateFrom.value)
  if (dateTo.value) query.set('date_to', dateTo.value)
  return query.toString()
}

async function load() {
  loading.value = true
  page.value = 1
  const res = await api<{ data: StockMovement[]; meta: { current_page: number; last_page: number } }>(`/stock-movements?${buildQuery(1)}`)
  movements.value = res.data
  hasMore.value = res.meta.current_page < res.meta.last_page
  loading.value = false
}

async function loadMore() {
  loadingMore.value = true
  const nextPage = page.value + 1
  const res = await api<{ data: StockMovement[]; meta: { current_page: number; last_page: number } }>(`/stock-movements?${buildQuery(nextPage)}`)
  movements.value = [...movements.value, ...res.data]
  page.value = nextPage
  hasMore.value = res.meta.current_page < res.meta.last_page
  loadingMore.value = false
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const totalIn = computed(() => movements.value.filter((m) => m.quantity > 0).reduce((sum, m) => sum + m.quantity, 0))
const totalOut = computed(() => movements.value.filter((m) => m.quantity < 0).reduce((sum, m) => sum + Math.abs(m.quantity), 0))

await load()
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Kardex</h1>
      <p class="text-sm text-txt-secondary">Histórico de todas as movimentações de estoque, com filtros por produto, tipo e período.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <StatCard label="Movimentações na página" :value="movements.length" :icon="History" tone="violet" />
      <StatCard label="Entradas (unid.)" :value="totalIn" :icon="ArrowUpCircle" tone="emerald" />
      <StatCard label="Saídas (unid.)" :value="totalOut" :icon="ArrowDownCircle" tone="danger" />
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card">
      <div class="min-w-[220px] flex-1">
        <label class="mb-1 block text-sm font-medium text-txt-secondary">Buscar produto</label>
        <label class="flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-txt-muted">
          <Search :size="15" />
          <input v-model="search" type="text" placeholder="Nome ou código..." class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none" @keyup.enter="load">
        </label>
      </div>
      <div class="w-48">
        <BaseSelect v-model="typeFilter" label="Tipo" :options="typeOptions" />
      </div>
      <div class="w-44">
        <BaseInput v-model="dateFrom" type="date" label="De" />
      </div>
      <div class="w-44">
        <BaseInput v-model="dateTo" type="date" label="Até" />
      </div>
      <BaseButton :block="false" @click="load">
        <RefreshCw :size="15" />
        Filtrar
      </BaseButton>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
      <div class="grid grid-cols-[1.1fr_1.8fr_0.9fr_0.8fr_1.6fr_1fr] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
        <span>Data</span>
        <span>Produto</span>
        <span>Tipo</span>
        <span class="text-right">Qtde.</span>
        <span>Origem / Motivo</span>
        <span>Usuário</span>
      </div>

      <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="movements.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">
        Nenhuma movimentação encontrada para o filtro selecionado.
      </div>
      <div
        v-for="movement in movements"
        v-else
        :key="movement.id"
        class="grid grid-cols-[1.1fr_1.8fr_0.9fr_0.8fr_1.6fr_1fr] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
      >
        <span class="text-sm text-txt-secondary">{{ formatDateTime(movement.created_at) }}</span>
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-txt-primary">{{ movement.product_name ?? '-' }}</p>
          <p class="text-[11px] text-txt-muted">Cód. {{ movement.product_code ?? '-' }}</p>
        </div>
        <span>
          <StatusBadge :label="movement.type_label" :tone="typeTone[movement.type]" />
        </span>
        <span class="text-right text-sm font-bold" :class="movement.quantity < 0 ? 'text-rose-600' : 'text-emerald-700'">
          {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
        </span>
        <button
          type="button"
          class="flex min-w-0 cursor-pointer items-center gap-1.5 text-left text-sm text-txt-secondary hover:text-txt-primary hover:underline"
          @click="openOriginModal(movement)"
        >
          <ShoppingCart v-if="movement.type === 'sale'" :size="13" class="shrink-0 text-txt-muted" />
          <span class="truncate">{{ movement.origin }}</span>
        </button>
        <span class="min-w-0 truncate text-sm text-txt-secondary">{{ movement.user_name ?? '-' }}</span>
      </div>

      <div v-if="hasMore" class="flex justify-center border-t border-border px-5 py-4">
        <BaseButton variant="ghost" :block="false" :loading="loadingMore" @click="loadMore">Carregar mais</BaseButton>
      </div>
    </div>

    <BaseModal :open="showOriginModal" title="Origem / motivo" subtitle="Detalhe completo da movimentação." @close="showOriginModal = false">
      <div v-if="originModalMovement" class="space-y-3 text-sm">
        <p class="text-txt-primary">{{ originModalMovement.origin }}</p>
        <div class="grid grid-cols-2 gap-3 border-t border-border pt-3 text-txt-secondary">
          <span>Produto: <strong class="text-txt-primary">{{ originModalMovement.product_name ?? '-' }}</strong></span>
          <span>Data: <strong class="text-txt-primary">{{ formatDateTime(originModalMovement.created_at) }}</strong></span>
          <span>Tipo: <strong class="text-txt-primary">{{ originModalMovement.type_label }}</strong></span>
          <span>Usuário: <strong class="text-txt-primary">{{ originModalMovement.user_name ?? '-' }}</strong></span>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
