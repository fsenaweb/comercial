import { defineAsyncComponent, type Component } from 'vue'
import {
  Banknote,
  Briefcase,
  Download,
  FileText,
  Landmark,
  LayoutGrid,
  Monitor,
  Package,
  Settings,
  ShoppingCart,
  Warehouse,
} from 'lucide-vue-next'

export type UserRole = 'admin' | 'cashier' | 'seller'

export interface ManualSection {
  id: string
  title: string
  icon: Component
  /** Sem `roles`, a seção é visível a qualquer papel autenticado. */
  roles?: UserRole[]
  component: Component
}

// Manifesto único do Manual do Usuário (F1): define a ordem do índice e o
// componente de conteúdo de cada seção. Cada seção é um componente Vue em
// components/manual/sections/ — texto simples, sem CMS/editor, porque o
// conteúdo muda tão devagar quanto o próprio sistema (ver 05-sprints.md,
// Sprint 7).
export const manualSections: ManualSection[] = [
  {
    id: 'introducao',
    title: 'Introdução e papéis',
    icon: LayoutGrid,
    component: defineAsyncComponent(() => import('~/components/manual/sections/IntroSection.vue')),
  },
  {
    id: 'caixa',
    title: 'Caixa',
    icon: Landmark,
    roles: ['admin', 'cashier'],
    component: defineAsyncComponent(() => import('~/components/manual/sections/CaixaSection.vue')),
  },
  {
    id: 'pdv',
    title: 'PDV (Ponto de Venda)',
    icon: Monitor,
    component: defineAsyncComponent(() => import('~/components/manual/sections/PdvSection.vue')),
  },
  {
    id: 'orcamentos',
    title: 'Orçamentos',
    icon: ShoppingCart,
    component: defineAsyncComponent(() => import('~/components/manual/sections/OrcamentosSection.vue')),
  },
  {
    id: 'cadastros',
    title: 'Cadastros',
    icon: Package,
    component: defineAsyncComponent(() => import('~/components/manual/sections/CadastrosSection.vue')),
  },
  {
    id: 'estoque',
    title: 'Estoque e Compras',
    icon: Warehouse,
    component: defineAsyncComponent(() => import('~/components/manual/sections/EstoqueSection.vue')),
  },
  {
    id: 'financeiro',
    title: 'Financeiro',
    icon: Banknote,
    roles: ['admin', 'cashier'],
    component: defineAsyncComponent(() => import('~/components/manual/sections/FinanceiroSection.vue')),
  },
  {
    id: 'relatorios',
    title: 'Relatórios',
    icon: FileText,
    component: defineAsyncComponent(() => import('~/components/manual/sections/RelatoriosSection.vue')),
  },
  {
    id: 'configuracoes',
    title: 'Configurações e usuários',
    icon: Settings,
    roles: ['admin'],
    component: defineAsyncComponent(() => import('~/components/manual/sections/ConfiguracoesSection.vue')),
  },
  {
    id: 'backup',
    title: 'Backup e restauração',
    icon: Download,
    roles: ['admin'],
    component: defineAsyncComponent(() => import('~/components/manual/sections/BackupSection.vue')),
  },
  {
    id: 'instalacao',
    title: 'Instalação e primeiro acesso',
    icon: Briefcase,
    roles: ['admin'],
    component: defineAsyncComponent(() => import('~/components/manual/sections/InstalacaoSection.vue')),
  },
]

export function manualSectionsForRole(role: UserRole | undefined): ManualSection[] {
  return manualSections.filter((section) => !section.roles || (role && section.roles.includes(role)))
}
