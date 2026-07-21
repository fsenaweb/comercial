<script setup lang="ts">
import { CheckCircle2, Plus } from 'lucide-vue-next'

interface Expense {
  id: number
  description: string
  category: string | null
  amount: string
  due_date: string
  status: 'pending' | 'paid'
  status_label: string
  is_overdue: boolean
  paid_at: string | null
}

const api = useApi()
const { parse, firstFieldError } = useApiError()
const { maskInput, toNumber } = useCurrencyMask()

const loading = ref(true)
const expenses = ref<Expense[]>([])
const statusFilter = ref<'all' | 'pending' | 'paid'>('all')

async function loadExpenses() {
  loading.value = true
  const { data } = await api<{ data: Expense[] }>('/expenses')
  expenses.value = data
  loading.value = false
}

const filteredExpenses = computed(() => {
  if (statusFilter.value === 'all') return expenses.value
  return expenses.value.filter((e) => e.status === statusFilter.value)
})

function formatAmount(value: string | number): string {
  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
}

// ---- MODAL: nova despesa ----
const showModal = ref(false)
const description = ref('')
const category = ref('')
const amountMasked = ref('R$ 0,00')
const dueDate = ref('')
const paidNow = ref(false)
const saving = ref(false)
const error = ref<unknown>(null)

function openModal() {
  description.value = ''
  category.value = ''
  amountMasked.value = 'R$ 0,00'
  dueDate.value = new Date().toISOString().slice(0, 10)
  paidNow.value = false
  error.value = null
  showModal.value = true
}

const canSubmit = computed(() => description.value.trim().length > 0 && toNumber(amountMasked.value) > 0 && dueDate.value.length > 0)

async function handleSubmit() {
  saving.value = true
  error.value = null
  try {
    await api('/expenses', {
      method: 'POST',
      body: {
        description: description.value,
        category: category.value || null,
        amount: toNumber(amountMasked.value),
        due_date: dueDate.value,
        paid_now: paidNow.value,
      },
    })
    showModal.value = false
    await loadExpenses()
  } catch (err) {
    error.value = err
  } finally {
    saving.value = false
  }
}

const settlingId = ref<number | null>(null)
const { confirmDialog } = useConfirmDialog()

async function handleSettle(expense: Expense) {
  const confirmed = await confirmDialog({
    title: 'Baixar despesa',
    message: `Confirmar a baixa da despesa "${expense.description}"?`,
    confirmLabel: 'Confirmar baixa',
  })
  if (!confirmed) return
  settlingId.value = expense.id
  try {
    await api(`/expenses/${expense.id}/settle`, { method: 'POST' })
    await loadExpenses()
  } finally {
    settlingId.value = null
  }
}

await loadExpenses()
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="font-display text-[30px] font-extrabold text-brand">Despesas</h1>
        <p class="text-sm text-txt-secondary">Despesas administrativas avulsas (aluguel, energia, internet...) - controle financeiro, não sai do caixa da loja.</p>
      </div>
      <BaseButton :block="false" @click="openModal">
        <Plus :size="15" />
        Nova despesa
      </BaseButton>
    </div>

    <div class="flex gap-2">
      <button
        v-for="option in [{ value: 'all', label: 'Todas' }, { value: 'pending', label: 'Pendentes' }, { value: 'paid', label: 'Pagas' }]"
        :key="option.value"
        type="button"
        class="cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-bold"
        :class="statusFilter === option.value ? 'border-ink bg-ink text-white' : 'border-border text-txt-secondary'"
        @click="statusFilter = option.value as typeof statusFilter"
      >
        {{ option.label }}
      </button>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
      <div class="grid grid-cols-[1.6fr_1.2fr_1fr_1fr_1fr_1fr] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
        <span>Descrição</span>
        <span>Categoria</span>
        <span class="text-right">Valor</span>
        <span>Vencimento</span>
        <span>Status</span>
        <span />
      </div>

      <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="filteredExpenses.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">Nenhuma despesa encontrada.</div>
      <div
        v-for="expense in filteredExpenses"
        v-else
        :key="expense.id"
        class="grid grid-cols-[1.6fr_1.2fr_1fr_1fr_1fr_1fr] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
      >
        <span class="truncate text-sm font-medium text-txt-primary">{{ expense.description }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ expense.category ?? '-' }}</span>
        <span class="text-right text-sm font-bold text-txt-primary">{{ formatAmount(expense.amount) }}</span>
        <span class="text-sm text-txt-secondary">{{ formatDate(expense.due_date) }}</span>
        <span class="flex items-center gap-1.5">
          <StatusBadge :label="expense.status_label" :tone="expense.status === 'paid' ? 'success' : 'neutral'" />
          <StatusBadge v-if="expense.is_overdue" label="Vencida" tone="danger" />
        </span>
        <span class="text-right">
          <BaseButton
            v-if="expense.status === 'pending'"
            :block="false"
            variant="ghost"
            :loading="settlingId === expense.id"
            @click="handleSettle(expense)"
          >
            <CheckCircle2 :size="15" />
            Dar baixa
          </BaseButton>
        </span>
      </div>
    </div>

    <!-- MODAL: NOVA DESPESA -->
    <BaseModal :open="showModal" title="Nova despesa" subtitle="Registre uma despesa administrativa avulsa." @close="showModal = false">
      <form class="space-y-4" @submit.prevent="handleSubmit">
        <BaseInput v-model="description" label="Descrição" placeholder="Ex.: Aluguel" :error="firstFieldError(error, 'description')" />
        <BaseInput v-model="category" label="Categoria (opcional)" placeholder="Ex.: Aluguel, Energia, Internet" :error="firstFieldError(error, 'category')" />
        <BaseInput
          :model-value="amountMasked"
          label="Valor"
          :error="firstFieldError(error, 'amount')"
          @update:model-value="amountMasked = maskInput($event)"
        />
        <BaseInput v-model="dueDate" type="date" label="Vencimento" :error="firstFieldError(error, 'due_date')" />
        <BaseSwitch v-model="paidNow" label="Já paga?" />

        <p v-if="error && !firstFieldError(error, 'description') && !firstFieldError(error, 'category') && !firstFieldError(error, 'amount') && !firstFieldError(error, 'due_date')" class="text-sm text-rose-600">
          {{ parse(error).message }}
        </p>

        <div class="flex justify-end gap-3">
          <BaseButton type="button" variant="ghost" :block="false" @click="showModal = false">Cancelar</BaseButton>
          <BaseButton type="submit" :disabled="!canSubmit" :loading="saving" :block="false">Salvar despesa</BaseButton>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
