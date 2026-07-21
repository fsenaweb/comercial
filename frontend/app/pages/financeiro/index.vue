<script setup lang="ts">
import { AlertTriangle, ChevronLeft, ChevronRight, CreditCard, PiggyBank, TrendingDown, Wallet } from 'lucide-vue-next'

interface PayableRow {
  description: string
  person_name: string | null
  due_date: string
  amount: string
}

interface ReceivableRow {
  customer_id: number
  customer_name: string | null
  balance: string
}

interface LowStockItem {
  id: number
  product_name: string
  product_code: string
  current_quantity: number
  min_quantity: number
}

interface Overview {
  month: string
  previous_month: string
  receivable_balance_total: string
  payables_due_this_month: string
  payables_paid_this_month: string
  payables_due_previous_month: string
  overdue_count: number
  overdue_total: string
  entries_in_month_count: number
  top_payables_this_month: PayableRow[]
  top_receivable_balances: ReceivableRow[]
  low_stock_count: number
  low_stock_items: LowStockItem[]
}

const api = useApi()
const loading = ref(true)
const overview = ref<Overview | null>(null)
const monthCursor = ref(new Date().toISOString().slice(0, 7))

async function load() {
  loading.value = true
  const { data } = await api<{ data: Overview }>('/financeiro/overview', { query: { month: monthCursor.value } })
  overview.value = data
  loading.value = false
}

function shiftMonth(delta: number) {
  const [year, month] = monthCursor.value.split('-').map(Number)
  const date = new Date(year!, month! - 1 + delta, 1)
  monthCursor.value = date.toISOString().slice(0, 7)
  load()
}

function formatAmount(value: string | number): string {
  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatMonthLabel(value: string): string {
  const [year, month] = value.split('-').map(Number)
  return new Date(year!, month! - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
}

const monthDiff = computed(() => {
  if (!overview.value) return null
  return Number(overview.value.payables_due_this_month) - Number(overview.value.payables_due_previous_month)
})

await load()
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Visão Financeira</h1>
      <p class="text-sm text-txt-secondary">Panorama do crediário, contas a pagar, despesas e estoque crítico.</p>
    </div>

    <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>

    <template v-else-if="overview">
      <!-- KPIs -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Saldo devedor do crediário" :value="formatAmount(overview.receivable_balance_total)" subtext="Total em aberto, todos os clientes" :icon="CreditCard" tone="violet" />
        <StatCard label="Despesas previstas no mês" :value="formatAmount(overview.payables_due_this_month)" subtext="Contas a pagar + despesas pendentes" :icon="Wallet" tone="sky" />
        <StatCard label="Despesas pagas no mês" :value="formatAmount(overview.payables_paid_this_month)" subtext="Já quitadas no período" :icon="PiggyBank" tone="emerald" />
        <StatCard
          label="Contas vencidas"
          :value="formatAmount(overview.overdue_total)"
          :subtext="`${overview.overdue_count} conta(s) em atraso`"
          :icon="AlertTriangle"
          :tone="overview.overdue_count > 0 ? 'danger' : 'emerald'"
        />
      </div>

      <!-- Planejamento do mês -->
      <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <div class="mb-4 flex items-center justify-between gap-4">
          <div>
            <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Planejamento do mês</p>
            <p class="font-display text-lg font-bold text-txt-primary">Contas a pagar e despesas</p>
          </div>
          <div class="flex items-center gap-2">
            <IconButton :icon="ChevronLeft" label="Mês anterior" @click="shiftMonth(-1)" />
            <span class="min-w-32 text-center text-sm font-bold text-txt-primary capitalize">{{ formatMonthLabel(overview.month) }}</span>
            <IconButton :icon="ChevronRight" label="Próximo mês" @click="shiftMonth(1)" />
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div class="rounded-xl border border-border p-4">
            <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Previstas</p>
            <p class="mt-1 font-display text-lg font-bold text-txt-primary">{{ formatAmount(overview.payables_due_this_month) }}</p>
          </div>
          <div class="rounded-xl border border-border p-4">
            <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Pagas</p>
            <p class="mt-1 font-display text-lg font-bold text-emerald-700">{{ formatAmount(overview.payables_paid_this_month) }}</p>
          </div>
          <div class="rounded-xl border border-border p-4">
            <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Lançamentos no mês</p>
            <p class="mt-1 font-display text-lg font-bold text-txt-primary">{{ overview.entries_in_month_count }}</p>
          </div>
        </div>

        <p class="mt-3 flex items-center gap-1.5 text-xs text-txt-muted">
          <TrendingDown v-if="monthDiff !== null && monthDiff < 0" :size="13" class="text-emerald-600" />
          Base comparativa: <strong class="text-txt-secondary">{{ formatMonthLabel(overview.previous_month) }}</strong> ({{ formatAmount(overview.payables_due_previous_month) }} previstas)
        </p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <!-- Maiores contas a pagar -->
        <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
          <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Saídas previstas</p>
          <p class="mb-4 font-display text-sm font-bold text-txt-primary">Maiores contas a pagar do mês</p>
          <div v-if="overview.top_payables_this_month.length === 0" class="rounded-xl border border-dashed border-border py-6 text-center text-sm text-txt-muted">
            Sem contas a pagar no período.
          </div>
          <div v-for="(row, index) in overview.top_payables_this_month" v-else :key="index" class="flex items-center justify-between gap-3 border-b border-border py-2.5 last:border-0">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-txt-primary">{{ row.description }}</p>
              <p class="text-[11px] text-txt-muted">{{ row.person_name ?? '-' }} · vence {{ formatDate(row.due_date) }}</p>
            </div>
            <span class="shrink-0 text-sm font-bold text-txt-primary">{{ formatAmount(row.amount) }}</span>
          </div>
        </div>

        <!-- Maiores saldos de crediário -->
        <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
          <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Crediário</p>
          <p class="mb-4 font-display text-sm font-bold text-txt-primary">Clientes com maior saldo devedor</p>
          <div v-if="overview.top_receivable_balances.length === 0" class="rounded-xl border border-dashed border-border py-6 text-center text-sm text-txt-muted">
            Nenhum cliente com saldo em aberto.
          </div>
          <NuxtLink
            v-for="row in overview.top_receivable_balances"
            v-else
            :key="row.customer_id"
            to="/financeiro/accounts-receivable"
            class="flex items-center justify-between gap-3 border-b border-border py-2.5 text-left last:border-0 hover:bg-surface-subtle"
          >
            <span class="truncate text-sm font-medium text-txt-primary">{{ row.customer_name ?? '-' }}</span>
            <span class="shrink-0 text-sm font-bold text-txt-primary">{{ formatAmount(row.balance) }}</span>
          </NuxtLink>
        </div>
      </div>

      <!-- Estoque crítico -->
      <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Risco operacional</p>
            <p class="font-display text-sm font-bold text-txt-primary">Estoque em atenção</p>
          </div>
          <StatusBadge :label="`${overview.low_stock_count} item(ns)`" :tone="overview.low_stock_count > 0 ? 'warning' : 'success'" />
        </div>
        <div v-if="overview.low_stock_items.length === 0" class="rounded-xl border border-dashed border-border py-6 text-center text-sm text-txt-muted">
          Nenhum produto crítico no momento.
        </div>
        <div v-for="item in overview.low_stock_items" v-else :key="item.id" class="flex items-center justify-between gap-3 border-b border-border py-2.5 last:border-0">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-txt-primary">{{ item.product_name }}</p>
            <p class="text-[11px] text-txt-muted">Cód. {{ item.product_code }}</p>
          </div>
          <span class="shrink-0 text-sm font-bold text-rose-600">{{ item.current_quantity }} / mín. {{ item.min_quantity }}</span>
        </div>
      </div>
    </template>
  </div>
</template>
