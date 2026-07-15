<script setup lang="ts">
import {
  ArrowLeft,
  ArrowRight,
  Boxes,
  Download,
  FileText,
  ShoppingCart,
} from 'lucide-vue-next'

interface ReportDefinition {
  key: string
  title: string
  description: string
  needsPeriod: boolean
}

interface CategoryDefinition {
  key: string
  title: string
  description: string
  icon: typeof ShoppingCart
  iconTone: string
  reports: ReportDefinition[]
}

interface ReportHeader {
  key: string
  label: string
}

interface ReportSummaryItem {
  label: string
  value: string
}

interface ReportPayload {
  title: string
  headers: ReportHeader[]
  rows: Record<string, string>[]
  summary?: ReportSummaryItem[]
}

const categories: CategoryDefinition[] = [
  {
    key: 'vendas',
    title: 'Vendas',
    description: 'Análise de faturamento, vendas de produtos, categorias e vendedores.',
    icon: ShoppingCart,
    iconTone: 'bg-emerald-100 text-emerald-600',
    reports: [
      { key: 'vendas_totais', title: 'Vendas Totais', description: 'Resumo geral do faturamento e vendas totais no período escolhido.', needsPeriod: true },
      { key: 'vendas_produto', title: 'Vendas por Produto', description: 'Classificação detalhada e volume dos produtos mais vendidos.', needsPeriod: true },
      { key: 'vendas_categoria', title: 'Vendas por Categoria', description: 'Performance comparativa das categorias cadastradas na loja.', needsPeriod: true },
      { key: 'vendas_vendedor', title: 'Vendas por Vendedor', description: 'Acompanhamento do volume de vendas faturadas por cada vendedor.', needsPeriod: true },
      { key: 'vendas_forma_pagamento', title: 'Vendas por Forma de Pagamento', description: 'Composição do faturamento por forma de pagamento, incluindo vendas com pagamento dividido.', needsPeriod: true },
      { key: 'lucro_bruto', title: 'Lucro Bruto por Produto', description: 'Demonstrativo de margem de lucro e rentabilidade unitária.', needsPeriod: true },
    ],
  },
  {
    key: 'estoque',
    title: 'Estoque',
    description: 'Visão quantitativa de produtos e valor de inventário.',
    icon: Boxes,
    iconTone: 'bg-sky-100 text-sky-600',
    reports: [
      { key: 'nivel_estoque', title: 'Nível de Estoque', description: 'Controle de estoque mínimo e alertas de reposição urgente.', needsPeriod: false },
      { key: 'valor_estoque', title: 'Valor do Estoque', description: 'Demonstrativo financeiro do custo total e preço de venda do inventário.', needsPeriod: false },
    ],
  },
]

const api = useApi()
const config = useRuntimeConfig()

const activeCategoryKey = ref(categories[0]!.key)
const activeReportKey = ref<string | null>(null)

const activeCategory = computed(() => categories.find((c) => c.key === activeCategoryKey.value)!)
const activeReport = computed(() => activeCategory.value.reports.find((r) => r.key === activeReportKey.value) ?? null)

function isoDaysAgo(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return date.toISOString().slice(0, 10)
}

const dateFrom = ref(isoDaysAgo(29))
const dateTo = ref(isoDaysAgo(0))

const loading = ref(false)
const report = ref<ReportPayload | null>(null)

function selectCategory(key: string) {
  activeCategoryKey.value = key
  activeReportKey.value = null
}

function openReport(key: string) {
  activeReportKey.value = key
  report.value = null
}

function backToList() {
  activeReportKey.value = null
  report.value = null
}

function buildQuery(): string {
  if (!activeReport.value?.needsPeriod) return ''
  const query = new URLSearchParams()
  if (dateFrom.value) query.set('date_from', dateFrom.value)
  if (dateTo.value) query.set('date_to', dateTo.value)
  return query.toString()
}

async function generateReport() {
  if (!activeReport.value) return
  loading.value = true
  const qs = buildQuery()
  const { data } = await api<{ data: ReportPayload }>(`/reports/catalog/${activeReport.value.key}${qs ? `?${qs}` : ''}`)
  report.value = data
  loading.value = false
}

function exportReport(format: 'pdf' | 'excel') {
  if (!activeReport.value) return
  const qs = buildQuery()
  const apiBase = (config.public.apiBase as string).replace(/\/$/, '')
  const url = `${apiBase}/reports/catalog/${activeReport.value.key}/export/${format}${qs ? `?${qs}` : ''}`
  window.open(url, '_blank')
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="font-display text-2xl font-bold text-txt-primary">Relatórios</h1>
      <p class="text-sm text-txt-secondary">Consulte métricas de vendas e estoque em tempo real.</p>
    </div>

    <template v-if="!activeReportKey">
      <div class="grid gap-4 sm:grid-cols-2">
        <button
          v-for="category in categories"
          :key="category.key"
          type="button"
          class="cursor-pointer rounded-2xl border p-5 text-left shadow-card transition"
          :class="category.key === activeCategoryKey ? 'border-brand bg-surface-raised' : 'border-border bg-surface-raised hover:border-border-strong'"
          @click="selectCategory(category.key)"
        >
          <span class="flex h-10 w-10 items-center justify-center rounded-xl" :class="category.iconTone">
            <component :is="category.icon" :size="20" />
          </span>
          <p class="mt-3 font-display text-base font-bold text-txt-primary">{{ category.title }}</p>
          <p class="mt-1 text-xs text-txt-muted">{{ category.description }}</p>
        </button>
      </div>

      <div class="space-y-3">
        <div class="border-b border-border pb-2.5">
          <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Categoria selecionada</span>
          <p class="font-display text-lg font-bold text-txt-primary">{{ activeCategory.title }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="rep in activeCategory.reports"
            :key="rep.key"
            class="flex flex-col gap-3 rounded-2xl border border-border bg-surface-raised p-5 shadow-card"
          >
            <p class="font-display text-sm font-bold text-txt-primary">{{ rep.title }}</p>
            <p class="flex-1 text-xs leading-relaxed text-txt-muted">{{ rep.description }}</p>
            <button
              type="button"
              class="flex cursor-pointer items-center gap-1.5 text-xs font-bold tracking-wide text-sky-600 uppercase"
              @click="openReport(rep.key)"
            >
              Acessar relatório
              <ArrowRight :size="13" />
            </button>
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="activeReport">
      <div class="flex items-center gap-4 rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <button
          type="button"
          class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-border text-txt-secondary hover:border-border-strong"
          @click="backToList"
        >
          <ArrowLeft :size="17" />
        </button>
        <div>
          <p class="text-[11px] font-bold tracking-wide text-sky-600 uppercase">Relatórios &gt; {{ activeCategory.title }}</p>
          <p class="font-display text-base font-bold text-txt-primary">{{ activeReport.title }}</p>
          <p class="text-xs text-txt-muted">{{ activeReport.description }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
        <div class="flex flex-col gap-4 p-5">
          <div v-if="activeReport.needsPeriod" class="grid max-w-lg grid-cols-2 gap-4">
            <BaseInput v-model="dateFrom" label="Data Início" type="date" />
            <BaseInput v-model="dateTo" label="Data Final" type="date" />
          </div>
          <div class="flex flex-wrap justify-end gap-2.5">
            <BaseButton variant="ghost" :block="false" @click="exportReport('excel')">
              <Download :size="15" />
              Exportar Excel
            </BaseButton>
            <BaseButton variant="ghost" :block="false" @click="exportReport('pdf')">
              <FileText :size="15" />
              Exportar PDF
            </BaseButton>
            <BaseButton :block="false" :loading="loading" loading-text="Gerando…" @click="generateReport">
              Gerar Relatório
            </BaseButton>
          </div>
        </div>

        <div v-if="report" class="border-t border-border">
          <div v-if="report.summary?.length" class="flex flex-wrap gap-6 border-b border-border bg-surface-subtle px-5 py-4">
            <div v-for="item in report.summary" :key="item.label">
              <p class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">{{ item.label }}</p>
              <p class="font-display text-base font-bold text-txt-primary">{{ item.value }}</p>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-surface-subtle text-left text-xs font-bold text-txt-secondary uppercase">
                  <th v-for="header in report.headers" :key="header.key" class="px-5 py-3">{{ header.label }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <tr v-for="(row, index) in report.rows" :key="index">
                  <td v-for="header in report.headers" :key="header.key" class="px-5 py-2.5 text-txt-primary">
                    {{ row[header.key] }}
                  </td>
                </tr>
                <tr v-if="report.rows.length === 0">
                  <td :colspan="report.headers.length" class="px-5 py-6 text-center text-txt-muted">
                    Nenhum dado encontrado para o período selecionado.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
