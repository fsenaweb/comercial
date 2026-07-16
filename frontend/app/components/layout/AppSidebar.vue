<script setup lang="ts">
import {
  BarChart3,
  Banknote,
  Briefcase,
  Building2,
  ChevronDown,
  ClipboardEdit,
  CreditCard,
  DollarSign,
  Gauge,
  History,
  Landmark,
  LayoutGrid,
  LogOut,
  Monitor,
  Package,
  PackagePlus,
  Receipt,
  Search,
  Settings,
  ShoppingCart,
  Tag,
  UserCheck,
  Users,
  Warehouse,
} from 'lucide-vue-next'

const auth = useAuthStore()
const route = useRoute()

const sidebarQuery = ref('')

const quickAccessLinks = [
  { to: '/', label: 'Dashboard', icon: LayoutGrid },
  { to: '/pos', label: 'PDV', icon: Monitor },
  { to: '/cash-register', label: 'Caixa', icon: DollarSign },
  { to: '/sales-history', label: 'Vendas', icon: Receipt },
  { to: '/quotes', label: 'Orçamentos', icon: ShoppingCart },
]

const comingSoonLinks: { label: string, icon: typeof ShoppingCart }[] = []

const estoqueLinks = [
  { to: '/stock/entries', label: 'Entradas de Estoque', icon: PackagePlus },
  { to: '/stock/adjustments', label: 'Ajuste de Estoque', icon: ClipboardEdit },
  { to: '/stock/kardex', label: 'Kardex', icon: History },
  { to: '/stock/labels', label: 'Etiquetas', icon: Tag },
]
const estoqueComingSoon: { label: string, icon: typeof Tag }[] = []

const cadastrosLinks = [
  { to: '/products', label: 'Produtos', icon: Package },
  { to: '/customers', label: 'Clientes', icon: Users },
  { to: '/suppliers', label: 'Fornecedores', icon: Briefcase },
]
const cadastrosComingSoon: { label: string, icon: typeof Tag }[] = []

const financeiroLinks = [
  { to: '/financeiro', label: 'Visão Financeira', icon: Gauge },
  { to: '/financeiro/accounts-receivable', label: 'Crediário', icon: CreditCard },
  { to: '/financeiro/accounts-payable', label: 'Contas a Pagar', icon: Banknote },
  { to: '/financeiro/expenses', label: 'Despesas', icon: Receipt },
]

const gestaoItems = computed(() => [
  { key: 'relatorios', label: 'Relatórios', icon: BarChart3, to: '/reports' },
  ...(auth.isAdmin ? [{ key: 'dados-empresa', label: 'Dados da Empresa', icon: Landmark, to: '/settings/store' }] : []),
  ...(auth.isAdmin ? [{ key: 'usuarios', label: 'Usuários e Permissões', icon: UserCheck, to: '/users' }] : []),
  ...(auth.isAdmin ? [{ key: 'configuracoes', label: 'Configurações', icon: Settings, to: '/settings/catalog' }] : []),
])

const query = computed(() => sidebarQuery.value.trim().toLowerCase())
function matches(label: string) {
  return query.value === '' || label.toLowerCase().includes(query.value)
}

const isCadastrosActive = computed(() => cadastrosLinks.some((link) => route.path.startsWith(link.to)))
const isEstoqueActive = computed(() => estoqueLinks.some((link) => route.path.startsWith(link.to)))
const isFinanceiroActive = computed(() => financeiroLinks.some((link) => route.path.startsWith(link.to)))
const isGestaoActive = computed(() => gestaoItems.value.some((item) => item.to && route.path.startsWith(item.to)))

// Só um grupo aberto por vez: abrir um fecha os outros (clique manual) e a
// navegação ressincroniza pro grupo da rota ativa (ou nenhum, se a página não
// pertencer a nenhum grupo).
type SidebarGroup = 'cadastros' | 'estoque' | 'financeiro' | 'gestao'

function activeGroup(): SidebarGroup | null {
  if (isCadastrosActive.value) return 'cadastros'
  if (isEstoqueActive.value) return 'estoque'
  if (isFinanceiroActive.value) return 'financeiro'
  if (isGestaoActive.value) return 'gestao'
  return null
}

const openGroup = ref<SidebarGroup | null>(activeGroup())

function toggleGroup(group: SidebarGroup) {
  openGroup.value = openGroup.value === group ? null : group
}

const cadastrosOpen = computed(() => openGroup.value === 'cadastros')
const estoqueOpen = computed(() => openGroup.value === 'estoque')
const financeiroOpen = computed(() => openGroup.value === 'financeiro')
const gestaoOpen = computed(() => openGroup.value === 'gestao')

watch(() => route.path, () => {
  openGroup.value = activeGroup()
})

async function handleLogout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>

<template>
  <aside class="flex h-screen w-64 shrink-0 flex-col border-r border-border bg-surface-raised">
    <div class="flex items-center gap-3 border-b border-border px-5 py-5">
      <img src="/logo.png" alt="Logo da loja" class="h-9 w-9 rounded-xl shadow-card">
      <div class="leading-tight">
        <span class="block font-display text-sm font-bold text-txt-primary">JP Parafusos</span>
        <span class="block text-[11px] font-medium text-txt-muted">Sistema Comercial</span>
      </div>
    </div>

    <div class="px-3.5 pt-3.5 pb-1">
      <label class="flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-txt-muted">
        <Search :size="15" />
        <input
          v-model="sidebarQuery"
          type="text"
          placeholder="Buscar no menu..."
          class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none"
        >
      </label>
    </div>

    <nav class="flex-1 space-y-0.5 overflow-y-auto px-3.5 pb-4">
      <p class="px-2.5 pt-3 pb-1.5 text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Acesso rápido</p>

      <NuxtLink
        v-for="link in quickAccessLinks"
        v-show="matches(link.label)"
        :key="link.to"
        :to="link.to"
        class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-secondary transition hover:bg-surface-subtle hover:text-txt-primary"
        active-class="!bg-brand !text-brand-ink !shadow-card"
      >
        <component :is="link.icon" :size="17" />
        {{ link.label }}
      </NuxtLink>

      <div
        v-for="link in comingSoonLinks"
        v-show="matches(link.label)"
        :key="link.label"
        title="Em breve"
        class="flex cursor-not-allowed items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-muted/70"
      >
        <component :is="link.icon" :size="17" />
        {{ link.label }}
        <span class="ml-auto text-[10px] font-bold tracking-wide uppercase opacity-70">Em breve</span>
      </div>

      <div class="my-2.5 border-t border-border" />

      <div>
        <button
          type="button"
          class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-sm font-semibold transition hover:bg-surface-subtle"
          :class="isCadastrosActive ? 'text-txt-primary' : 'text-txt-secondary hover:text-txt-primary'"
          @click="toggleGroup('cadastros')"
        >
          <span class="flex items-center gap-3">
            <Users :size="17" />
            Cadastros
          </span>
          <ChevronDown :size="15" class="transition-transform" :class="{ '-rotate-90': !cadastrosOpen }" />
        </button>

        <div v-show="cadastrosOpen" class="mt-0.5 ml-3.5 space-y-0.5 border-l border-border pl-2.5">
          <NuxtLink
            v-for="link in cadastrosLinks"
            v-show="matches(link.label)"
            :key="link.to"
            :to="link.to"
            class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-secondary transition hover:bg-surface-subtle hover:text-txt-primary"
            active-class="!bg-brand !text-brand-ink !shadow-card"
          >
            <component :is="link.icon" :size="16" />
            {{ link.label }}
          </NuxtLink>
          <div
            v-for="link in cadastrosComingSoon"
            v-show="matches(link.label)"
            :key="link.label"
            title="Em breve"
            class="flex cursor-not-allowed items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-muted/70"
          >
            <component :is="link.icon" :size="16" />
            {{ link.label }}
            <span class="ml-auto text-[10px] font-bold tracking-wide uppercase opacity-70">Em breve</span>
          </div>
        </div>
      </div>

      <div>
        <button
          type="button"
          class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-sm font-semibold transition hover:bg-surface-subtle"
          :class="isEstoqueActive ? 'text-txt-primary' : 'text-txt-secondary hover:text-txt-primary'"
          @click="toggleGroup('estoque')"
        >
          <span class="flex items-center gap-3">
            <Warehouse :size="17" />
            Estoque e Compras
          </span>
          <ChevronDown :size="15" class="transition-transform" :class="{ '-rotate-90': !estoqueOpen }" />
        </button>

        <div v-show="estoqueOpen" class="mt-0.5 ml-3.5 space-y-0.5 border-l border-border pl-2.5">
          <NuxtLink
            v-for="link in estoqueLinks"
            v-show="matches(link.label)"
            :key="link.to"
            :to="link.to"
            class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-secondary transition hover:bg-surface-subtle hover:text-txt-primary"
            active-class="!bg-brand !text-brand-ink !shadow-card"
          >
            <component :is="link.icon" :size="16" />
            {{ link.label }}
          </NuxtLink>
          <div
            v-for="link in estoqueComingSoon"
            v-show="matches(link.label)"
            :key="link.label"
            title="Em breve"
            class="flex cursor-not-allowed items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-muted/70"
          >
            <component :is="link.icon" :size="16" />
            {{ link.label }}
            <span class="ml-auto text-[10px] font-bold tracking-wide uppercase opacity-70">Em breve</span>
          </div>
        </div>
      </div>

      <div>
        <button
          type="button"
          class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-sm font-semibold transition hover:bg-surface-subtle"
          :class="isFinanceiroActive ? 'text-txt-primary' : 'text-txt-secondary hover:text-txt-primary'"
          @click="toggleGroup('financeiro')"
        >
          <span class="flex items-center gap-3">
            <CreditCard :size="17" />
            Financeiro
          </span>
          <ChevronDown :size="15" class="transition-transform" :class="{ '-rotate-90': !financeiroOpen }" />
        </button>

        <div v-show="financeiroOpen" class="mt-0.5 ml-3.5 space-y-0.5 border-l border-border pl-2.5">
          <NuxtLink
            v-for="link in financeiroLinks"
            v-show="matches(link.label)"
            :key="link.to"
            :to="link.to"
            class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-secondary transition hover:bg-surface-subtle hover:text-txt-primary"
            active-class="!bg-brand !text-brand-ink !shadow-card"
          >
            <component :is="link.icon" :size="16" />
            {{ link.label }}
          </NuxtLink>
        </div>
      </div>

      <div>
        <button
          type="button"
          class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-sm font-semibold transition hover:bg-surface-subtle"
          :class="isGestaoActive ? 'text-txt-primary' : 'text-txt-secondary hover:text-txt-primary'"
          @click="toggleGroup('gestao')"
        >
          <span class="flex items-center gap-3">
            <Building2 :size="17" />
            Administração
          </span>
          <ChevronDown :size="15" class="transition-transform" :class="{ '-rotate-90': !gestaoOpen }" />
        </button>

        <div v-if="gestaoOpen" class="mt-0.5 ml-3.5 space-y-0.5 border-l border-border pl-2.5">
          <template v-for="item in gestaoItems" :key="item.key">
            <NuxtLink
              v-if="item.to"
              v-show="matches(item.label)"
              :to="item.to"
              class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-secondary transition hover:bg-surface-subtle hover:text-txt-primary"
              active-class="!bg-brand !text-brand-ink !shadow-card"
            >
              <component :is="item.icon" :size="16" />
              {{ item.label }}
            </NuxtLink>
            <div
              v-else
              v-show="matches(item.label)"
              title="Em breve"
              class="flex cursor-not-allowed items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-muted/70"
            >
              <component :is="item.icon" :size="16" />
              {{ item.label }}
              <span class="ml-auto text-[10px] font-bold tracking-wide uppercase opacity-70">Em breve</span>
            </div>
          </template>
        </div>
      </div>

    </nav>

    <div class="space-y-0.5 border-t border-border p-3">
      <NuxtLink
        to="/pos"
        class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-secondary transition hover:bg-surface-subtle hover:text-txt-primary"
      >
        <Monitor :size="17" />
        Ir para o PDV
      </NuxtLink>
      <button
        class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
        @click="handleLogout"
      >
        <LogOut :size="17" />
        Sair do sistema
      </button>
    </div>
  </aside>
</template>
