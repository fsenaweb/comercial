<script setup lang="ts">
import {
  Banknote,
  Boxes,
  Briefcase,
  DollarSign,
  Package,
  PiggyBank,
  Plus,
  RefreshCw,
  ShoppingCart,
  TrendingUp,
  Users,
} from 'lucide-vue-next'

interface Customer {
  id: number
  created_at: string
}

interface SellerToday {
  seller_id: number
  seller_name: string
  total: string
}

interface MonthlyCount {
  month: string
  count: number
}

interface MonthlyTotal {
  month: string
  total: string
}

interface DashboardSummary {
  today_total: string
  today_sales_count: number
  sales_by_seller_today: SellerToday[]
  low_stock_count: number
  monthly_sales_count: MonthlyCount[]
  monthly_sales_total: MonthlyTotal[]
}

const auth = useAuthStore()
const api = useApi()
const { format } = useCurrencyMask()
const productsApi = useResourceApi<{ id: number }>('products')
const customersApi = useResourceApi<Customer>('customers')

const products = ref<{ id: number }[]>([])
const customers = ref<Customer[]>([])
const summary = ref<DashboardSummary | null>(null)
const refreshing = ref(false)

function money(value: string | number): string {
  return format(Math.round(Number(value) * 100))
}

function monthLabel(month: string): string {
  const [year, monthNumber] = month.split('-')
  const date = new Date(Number(year), Number(monthNumber) - 1, 1)
  return date.toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '')
}

async function load() {
  const [productsResult, customersResult, summaryResult] = await Promise.all([
    productsApi.list(),
    customersApi.list(),
    api<{ data: DashboardSummary }>('/reports/dashboard-summary'),
  ])
  products.value = productsResult
  customers.value = customersResult
  summary.value = summaryResult.data
}

async function handleRefresh() {
  refreshing.value = true
  await load()
  refreshing.value = false
}

const lowStockCount = computed(() => summary.value?.low_stock_count ?? 0)

const newCustomersThisMonth = computed(() => {
  const now = new Date()
  return customers.value.filter((c) => {
    const createdAt = new Date(c.created_at)
    return createdAt.getMonth() === now.getMonth() && createdAt.getFullYear() === now.getFullYear()
  }).length
})

const currentMonthCount = computed(() => summary.value?.monthly_sales_count.at(-1)?.count ?? 0)
const currentMonthTotal = computed(() => summary.value?.monthly_sales_total.at(-1)?.total ?? '0')

const monthlyCountChart = computed(
  () => summary.value?.monthly_sales_count.map((m) => ({ label: monthLabel(m.month), value: m.count })) ?? [],
)
const monthlyTotalChart = computed(
  () => summary.value?.monthly_sales_total.map((m) => ({ label: monthLabel(m.month), value: Number(m.total) })) ?? [],
)

const shortcuts = [
  { to: '/products', label: 'Produtos', icon: Package, tone: 'emerald' },
  { to: '/customers', label: 'Clientes', icon: Users, tone: 'violet' },
  { to: '/suppliers', label: 'Fornecedores', icon: Briefcase, tone: 'sky' },
] as const

await load()
</script>

<template>
  <div class="space-y-7">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <p class="max-w-2xl text-[15px] leading-relaxed text-txt-secondary">
        Olá, <strong class="text-txt-primary">{{ auth.user?.name }}</strong>. Aqui você acompanha o que pede
        atenção hoje — vendas do dia, estoque baixo e a base de clientes.
      </p>
      <div class="flex shrink-0 gap-2.5">
        <BaseButton variant="ghost" :block="false" :loading="refreshing" loading-text="Atualizando…" @click="handleRefresh">
          <RefreshCw :size="15" />
          Atualizar painel
        </BaseButton>
        <BaseButton :block="false" @click="navigateTo('/pos')">
          <Plus :size="15" />
          Nova Venda
        </BaseButton>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card" :class="{ '!border-l-4 !border-l-amber-400': lowStockCount > 0 }">
        <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Reposição de estoque</span>
        <p class="mt-1.5 font-display text-2xl font-bold text-txt-primary">{{ lowStockCount }}</p>
        <span class="text-xs text-txt-muted">{{ lowStockCount > 0 ? 'Variação(ões) abaixo do mínimo' : 'Estoque em dia' }}</span>
      </div>
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Relação com clientes</span>
        <p class="mt-1.5 font-display text-2xl font-bold text-txt-primary">{{ customers.length }}</p>
        <span class="text-xs text-txt-muted">{{ newCustomersThisMonth }} novo(s) cliente(s) no mês</span>
      </div>
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Vendas hoje</span>
        <p class="mt-1.5 font-display text-2xl font-bold text-txt-primary">{{ summary?.today_sales_count ?? 0 }}</p>
        <span class="text-xs text-txt-muted">Venda(s) concluída(s) hoje</span>
      </div>
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Faturamento hoje</span>
        <p class="mt-1.5 font-display text-2xl font-bold text-txt-primary">{{ money(summary?.today_total ?? '0') }}</p>
        <span class="text-xs text-txt-muted">Total vendido no dia</span>
      </div>
    </div>

    <div>
      <span class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Resumo base</span>
      <h2 class="font-display text-xl font-bold text-txt-primary">Visão geral do negócio</h2>
      <p class="text-sm text-txt-secondary">Indicadores para acompanhar volume, base de clientes e força comercial.</p>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <StatCard label="Produtos cadastrados" :value="products.length" subtext="Base total de produtos" :icon="Package" tone="emerald" />
      <StatCard
        label="Clientes ativos"
        :value="customers.length"
        :subtext="`${newCustomersThisMonth} novo(s) no mês`"
        :icon="Users"
        tone="sky"
      />
      <StatCard label="Total de vendas" :value="currentMonthCount" subtext="Vendas concluídas no mês" :icon="ShoppingCart" tone="violet" />
      <StatCard label="Valor total vendido" :value="money(currentMonthTotal)" subtext="Faturamento do mês" :icon="DollarSign" tone="amber" />
    </div>

    <div>
      <span class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Desempenho comercial</span>
      <h2 class="font-display text-xl font-bold text-txt-primary">Análise de vendas</h2>
      <p class="text-sm text-txt-secondary">Evolução das vendas concluídas nos últimos 6 meses.</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <p class="font-display text-sm font-bold text-txt-primary">Vendas por mês</p>
        <p class="text-xs text-txt-muted">Quantidade de vendas nos últimos 6 meses</p>
        <div class="mt-4">
          <BarSparkline :data="monthlyCountChart" tone="sky" />
        </div>
      </div>
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <p class="font-display text-sm font-bold text-txt-primary">Valor em vendas por mês</p>
        <p class="text-xs text-txt-muted">Evolução do faturamento nos últimos 6 meses</p>
        <div class="mt-4">
          <BarSparkline :data="monthlyTotalChart" tone="brand" :format-value="money" />
        </div>
      </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
      <p class="font-display text-sm font-bold text-txt-primary">Vendas por vendedor hoje</p>
      <p class="text-xs text-txt-muted">Faturamento de cada vendedor no dia</p>
      <div v-if="summary?.sales_by_seller_today.length" class="mt-4 divide-y divide-border">
        <div
          v-for="seller in summary.sales_by_seller_today"
          :key="seller.seller_id"
          class="flex items-center justify-between py-2.5 text-sm"
        >
          <span class="font-medium text-txt-primary">{{ seller.seller_name }}</span>
          <span class="font-display font-bold text-txt-primary">{{ money(seller.total) }}</span>
        </div>
      </div>
      <div v-else class="mt-4 flex h-16 items-center justify-center rounded-xl bg-surface-subtle text-xs text-txt-muted">
        Nenhuma venda registrada hoje ainda
      </div>
    </div>

    <div class="flex items-center gap-3">
      <span class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Atalhos do ecossistema</span>
      <div class="h-px flex-1 bg-border" />
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div title="Em breve" class="flex cursor-not-allowed items-center gap-3 rounded-2xl border border-dashed border-border bg-surface-raised p-4 opacity-60">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-surface-subtle text-txt-muted"><TrendingUp :size="16" /></span>
        <span class="text-xs font-bold tracking-wide text-txt-secondary uppercase">Vendas</span>
      </div>
      <div title="Em breve" class="flex cursor-not-allowed items-center gap-3 rounded-2xl border border-dashed border-border bg-surface-raised p-4 opacity-60">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-surface-subtle text-txt-muted"><Banknote :size="16" /></span>
        <span class="text-xs font-bold tracking-wide text-txt-secondary uppercase">Contas</span>
      </div>
      <NuxtLink
        to="/reports"
        class="flex items-center gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card transition hover:border-border-strong hover:shadow-md"
      >
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><PiggyBank :size="16" /></span>
        <span class="text-xs font-bold tracking-wide text-txt-secondary uppercase">Relatórios</span>
      </NuxtLink>
      <NuxtLink
        v-if="auth.isAdmin"
        to="/settings/catalog"
        class="flex items-center gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card transition hover:border-border-strong hover:shadow-md"
      >
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600"><Boxes :size="16" /></span>
        <span class="text-xs font-bold tracking-wide text-txt-secondary uppercase">Ajustes</span>
      </NuxtLink>
    </div>

    <div class="flex items-center gap-3">
      <span class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Cadastros</span>
      <div class="h-px flex-1 bg-border" />
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      <NuxtLink
        v-for="shortcut in shortcuts"
        :key="shortcut.to"
        :to="shortcut.to"
        class="flex items-center gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card transition hover:border-border-strong hover:shadow-md"
      >
        <span
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
          :class="{
            'bg-emerald-100 text-emerald-600': shortcut.tone === 'emerald',
            'bg-sky-100 text-sky-600': shortcut.tone === 'sky',
            'bg-violet-100 text-violet-600': shortcut.tone === 'violet',
          }"
        >
          <component :is="shortcut.icon" :size="20" />
        </span>
        <span class="text-sm font-semibold text-txt-primary">{{ shortcut.label }}</span>
      </NuxtLink>
    </div>
  </div>
</template>
