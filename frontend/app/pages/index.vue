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

interface Variation {
  current_quantity: number
  min_quantity: number | null
}

interface Product {
  id: number
  variations?: Variation[]
}

interface Customer {
  id: number
  created_at: string
}

const auth = useAuthStore()
const productsApi = useResourceApi<Product>('products')
const customersApi = useResourceApi<Customer>('customers')

const products = ref<Product[]>([])
const customers = ref<Customer[]>([])
const refreshing = ref(false)

async function load() {
  const [productsResult, customersResult] = await Promise.all([productsApi.list(), customersApi.list()])
  products.value = productsResult
  customers.value = customersResult
}

async function handleRefresh() {
  refreshing.value = true
  await load()
  refreshing.value = false
}

const lowStockCount = computed(() =>
  products.value.reduce((total, product) => {
    const lowVariations = (product.variations ?? []).filter(
      (v) => v.min_quantity !== null && v.current_quantity <= v.min_quantity,
    )
    return total + lowVariations.length
  }, 0),
)

const newCustomersThisMonth = computed(() => {
  const now = new Date()
  return customers.value.filter((c) => {
    const createdAt = new Date(c.created_at)
    return createdAt.getMonth() === now.getMonth() && createdAt.getFullYear() === now.getFullYear()
  }).length
})

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
        atenção hoje — o estoque baixo e os clientes novos. O restante (vendas, caixa, relatórios) chega nas
        próximas fases.
      </p>
      <div class="flex shrink-0 gap-2.5">
        <BaseButton variant="ghost" :block="false" :loading="refreshing" loading-text="Atualizando…" @click="handleRefresh">
          <RefreshCw :size="15" />
          Atualizar painel
        </BaseButton>
        <BaseButton :block="false" disabled title="Disponível quando o PDV for lançado">
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
      <div class="rounded-2xl border border-dashed border-border bg-surface-raised p-5 opacity-70">
        <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Caixa vendido no mês</span>
        <p class="mt-1.5 font-display text-2xl font-bold text-txt-muted">—</p>
        <span class="text-xs text-txt-muted">Módulo de Caixa em breve</span>
      </div>
      <div class="rounded-2xl border border-dashed border-border bg-surface-raised p-5 opacity-70">
        <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Vendas no mês</span>
        <p class="mt-1.5 font-display text-2xl font-bold text-txt-muted">—</p>
        <span class="text-xs text-txt-muted">Módulo de Vendas em breve</span>
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
      <div class="rounded-2xl border border-dashed border-border bg-surface-raised p-5 opacity-70">
        <div class="flex items-start justify-between gap-3">
          <div>
            <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Total de vendas</span>
            <p class="mt-1.5 font-display text-xl font-bold text-txt-muted">—</p>
            <span class="text-xs text-txt-muted">Em breve</span>
          </div>
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface-subtle text-txt-muted">
            <ShoppingCart :size="18" />
          </span>
        </div>
      </div>
      <div class="rounded-2xl border border-dashed border-border bg-surface-raised p-5 opacity-70">
        <div class="flex items-start justify-between gap-3">
          <div>
            <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Valor total vendido</span>
            <p class="mt-1.5 font-display text-xl font-bold text-txt-muted">—</p>
            <span class="text-xs text-txt-muted">Em breve</span>
          </div>
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface-subtle text-txt-muted">
            <DollarSign :size="18" />
          </span>
        </div>
      </div>
    </div>

    <div>
      <span class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Desempenho comercial</span>
      <h2 class="font-display text-xl font-bold text-txt-primary">Análise de vendas</h2>
      <p class="text-sm text-txt-secondary">Chega junto com o módulo de PDV e Caixa (próximas fases).</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <p class="font-display text-sm font-bold text-txt-primary">Vendas por mês</p>
        <p class="text-xs text-txt-muted">Quantidade de vendas ao longo do ano</p>
        <div class="mt-4 flex h-36 items-center justify-center rounded-xl bg-surface-subtle text-xs text-txt-muted">
          Sem dados ainda
        </div>
      </div>
      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <p class="font-display text-sm font-bold text-txt-primary">Valor em vendas por mês</p>
        <p class="text-xs text-txt-muted">Evolução do faturamento mensal</p>
        <div class="mt-4 flex h-36 items-center justify-center rounded-xl bg-surface-subtle text-xs text-txt-muted">
          Sem dados ainda
        </div>
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
      <div title="Em breve" class="flex cursor-not-allowed items-center gap-3 rounded-2xl border border-dashed border-border bg-surface-raised p-4 opacity-60">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-surface-subtle text-txt-muted"><PiggyBank :size="16" /></span>
        <span class="text-xs font-bold tracking-wide text-txt-secondary uppercase">Relatórios</span>
      </div>
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
