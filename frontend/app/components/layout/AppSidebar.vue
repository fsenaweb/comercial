<script setup lang="ts">
import {
  BarChart3,
  Briefcase,
  Building2,
  ChevronDown,
  CreditCard,
  DollarSign,
  FileText,
  HelpCircle,
  Landmark,
  LayoutGrid,
  LogOut,
  Monitor,
  Package,
  Search,
  Settings,
  ShoppingCart,
  UserCheck,
  UserCog,
  Users,
  Warehouse,
  Wrench,
} from 'lucide-vue-next'

const auth = useAuthStore()
const route = useRoute()

const cadastrosOpen = ref(true)
const estoqueOpen = ref(false)
const financeiroOpen = ref(false)
const gestaoOpen = ref(false)
const sidebarQuery = ref('')

const quickAccessLinks = [
  { to: '/', label: 'Dashboard', icon: LayoutGrid },
  { to: '/cash-register', label: 'Caixa', icon: DollarSign },
]

const comingSoonLinks = [
  { label: 'PDV', icon: Monitor },
  { label: 'Pedidos', icon: ShoppingCart },
  { label: 'Ordens de Serviço', icon: Wrench },
  { label: 'Notas Fiscais', icon: FileText },
]

const cadastrosLinks = [
  { to: '/products', label: 'Produtos', icon: Package },
  { to: '/customers', label: 'Clientes', icon: Users },
  { to: '/suppliers', label: 'Fornecedores', icon: Briefcase },
]
const cadastrosComingSoon = [{ label: 'Vendedores', icon: UserCog }]

const gestaoItems = computed(() => [
  { key: 'relatorios', label: 'Relatórios', icon: BarChart3, to: null },
  ...(auth.isAdmin ? [{ key: 'dados-empresa', label: 'Dados da Empresa', icon: Landmark, to: '/settings/store' }] : []),
  ...(auth.isAdmin ? [{ key: 'usuarios', label: 'Usuários e Permissões', icon: UserCheck, to: '/users' }] : []),
  ...(auth.isAdmin ? [{ key: 'configuracoes', label: 'Configurações', icon: Settings, to: '/settings/catalog' }] : []),
])

const query = computed(() => sidebarQuery.value.trim().toLowerCase())
function matches(label: string) {
  return query.value === '' || label.toLowerCase().includes(query.value)
}

const isCadastrosActive = computed(() => cadastrosLinks.some((link) => route.path.startsWith(link.to)))
const isGestaoActive = computed(() => gestaoItems.value.some((item) => item.to && route.path.startsWith(item.to)))

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
          @click="cadastrosOpen = !cadastrosOpen"
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

      <button
        type="button"
        class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-secondary transition hover:bg-surface-subtle"
        @click="estoqueOpen = !estoqueOpen"
      >
        <span class="flex items-center gap-3">
          <Warehouse :size="17" />
          Estoque e Compras
        </span>
        <ChevronDown :size="15" class="transition-transform" :class="{ '-rotate-90': !estoqueOpen }" />
      </button>
      <p v-if="estoqueOpen" class="px-2.5 py-1.5 pl-9 text-xs text-txt-muted italic">Em breve</p>

      <button
        type="button"
        class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-secondary transition hover:bg-surface-subtle"
        @click="financeiroOpen = !financeiroOpen"
      >
        <span class="flex items-center gap-3">
          <CreditCard :size="17" />
          Financeiro
        </span>
        <ChevronDown :size="15" class="transition-transform" :class="{ '-rotate-90': !financeiroOpen }" />
      </button>
      <p v-if="financeiroOpen" class="px-2.5 py-1.5 pl-9 text-xs text-txt-muted italic">Em breve</p>

      <div>
        <button
          type="button"
          class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-sm font-semibold transition hover:bg-surface-subtle"
          :class="isGestaoActive ? 'text-txt-primary' : 'text-txt-secondary hover:text-txt-primary'"
          @click="gestaoOpen = !gestaoOpen"
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

      <p class="px-2.5 pt-3.5 pb-1.5 text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Suporte</p>
      <div
        v-show="matches('Ajuda e Sugestões')"
        title="Em breve"
        class="flex cursor-not-allowed items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-muted/70"
      >
        <HelpCircle :size="17" />
        Ajuda e Sugestões
      </div>
    </nav>

    <div class="space-y-0.5 border-t border-border p-3">
      <div title="Em breve" class="flex cursor-not-allowed items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold text-txt-muted/70">
        <Monitor :size="17" />
        Ir para o PDV
      </div>
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
