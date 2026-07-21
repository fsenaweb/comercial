<script setup lang="ts">
import { ArrowLeft, CheckCircle2, Plus, Wallet } from 'lucide-vue-next'

interface Supplier {
  id: number
  corporate_name: string
  trade_name: string | null
}

interface PayableInstallment {
  id: number
  number: number
  amount: string
  due_date: string
  status: 'pending' | 'paid'
  status_label: string
  is_overdue: boolean
  paid_at: string | null
}

interface AccountsPayable {
  id: number
  supplier_id: number
  supplier_name: string | null
  description: string
  total_amount: string
  installments_count: number
  status: 'open' | 'paid'
  status_label: string
  has_overdue_installment: boolean
  installments: PayableInstallment[]
  notes: string | null
}

const api = useApi()
const { parse, firstFieldError } = useApiError()
const { maskInput, toNumber, format } = useCurrencyMask()
const { split, sum } = useInstallmentSplit()

const view = ref<'list' | 'form' | 'detail'>('list')
const loading = ref(true)
const payables = ref<AccountsPayable[]>([])
const suppliers = ref<Supplier[]>([])
const statusFilter = ref<'all' | 'open' | 'paid'>('all')

async function loadAll() {
  loading.value = true
  const [payablesRes, suppliersRes] = await Promise.all([
    api<{ data: AccountsPayable[] }>('/accounts-payable'),
    api<{ data: Supplier[] }>('/suppliers'),
  ])
  payables.value = payablesRes.data
  suppliers.value = suppliersRes.data
  loading.value = false
}

function supplierLabel(supplier: Supplier): string {
  return supplier.trade_name ?? supplier.corporate_name
}

const filteredPayables = computed(() => {
  if (statusFilter.value === 'all') return payables.value
  return payables.value.filter((p) => p.status === statusFilter.value)
})

function formatAmount(value: string | number): string {
  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
}

// ---- FORM: nova conta a pagar ----
const supplierId = ref<number | null>(null)
const description = ref('')
const totalMasked = ref('R$ 0,00')
const installmentsCount = ref(1)
const firstDueDate = ref('')
const rows = ref<{ number: number, amountMasked: string, due_date: string }[]>([])
const saving = ref(false)
const error = ref<unknown>(null)

function openForm() {
  supplierId.value = null
  description.value = ''
  totalMasked.value = 'R$ 0,00'
  installmentsCount.value = 1
  firstDueDate.value = new Date().toISOString().slice(0, 10)
  rows.value = []
  error.value = null
  view.value = 'form'
}

function generateRows() {
  const total = toNumber(totalMasked.value)
  if (total <= 0 || installmentsCount.value < 1 || !firstDueDate.value) return
  const suggestion = split(total, installmentsCount.value, firstDueDate.value)
  rows.value = suggestion.map((s) => ({ number: s.number, amountMasked: format(Math.round(s.amount * 100)), due_date: s.due_date }))
}

const rowsSum = computed(() => sum(rows.value.map((r) => ({ number: r.number, amount: toNumber(r.amountMasked), due_date: r.due_date }))))
const totalValue = computed(() => toNumber(totalMasked.value))
const sumMatches = computed(() => rows.value.length > 0 && Math.abs(rowsSum.value - totalValue.value) < 0.005)

const canSubmit = computed(() => supplierId.value !== null && description.value.trim().length > 0 && sumMatches.value)

async function handleSubmit() {
  saving.value = true
  error.value = null
  try {
    await api('/accounts-payable', {
      method: 'POST',
      body: {
        supplier_id: supplierId.value,
        description: description.value,
        total_amount: totalValue.value,
        installments: rows.value.map((r) => ({ number: r.number, amount: toNumber(r.amountMasked), due_date: r.due_date })),
      },
    })
    view.value = 'list'
    await loadAll()
  } catch (err) {
    error.value = err
  } finally {
    saving.value = false
  }
}

// ---- DETAIL ----
const selected = ref<AccountsPayable | null>(null)

async function openDetail(payable: AccountsPayable) {
  const { data } = await api<{ data: AccountsPayable }>(`/accounts-payable/${payable.id}`)
  selected.value = data
  view.value = 'detail'
}

const settlingId = ref<number | null>(null)

async function handleSettle(installment: PayableInstallment) {
  settlingId.value = installment.id
  try {
    await api(`/accounts-payable/installments/${installment.id}/settle`, { method: 'POST' })
    if (selected.value) await openDetail(selected.value)
    await loadAll()
  } finally {
    settlingId.value = null
  }
}

await loadAll()
</script>

<template>
  <div class="space-y-5">
    <!-- LIST VIEW -->
    <div v-if="view === 'list'" class="space-y-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="font-display text-[30px] font-extrabold text-brand">Contas a Pagar</h1>
          <p class="text-sm text-txt-secondary">Controle financeiro de dívidas com fornecedores — pago por boleto/transferência, não sai do caixa da loja.</p>
        </div>
        <BaseButton :block="false" @click="openForm">
          <Plus :size="15" />
          Nova conta a pagar
        </BaseButton>
      </div>

      <div class="flex gap-2">
        <button
          v-for="option in [{ value: 'all', label: 'Todas' }, { value: 'open', label: 'Em aberto' }, { value: 'paid', label: 'Quitadas' }]"
          :key="option.value"
          type="button"
          class="cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-bold"
          :class="statusFilter === option.value ? 'border-txt-primary bg-txt-primary text-white' : 'border-border text-txt-secondary'"
          @click="statusFilter = option.value as typeof statusFilter"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
        <div class="grid grid-cols-[1.6fr_1.8fr_1fr_1fr_1fr] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
          <span>Fornecedor</span>
          <span>Descrição</span>
          <span class="text-right">Total</span>
          <span>Parcelas</span>
          <span>Status</span>
        </div>

        <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
        <div v-else-if="filteredPayables.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">Nenhuma conta encontrada.</div>
        <button
          v-for="payable in filteredPayables"
          v-else
          :key="payable.id"
          type="button"
          class="cursor-pointer grid w-full grid-cols-[1.6fr_1.8fr_1fr_1fr_1fr] items-center gap-2 border-b border-border px-5 py-3 text-left last:border-0 hover:bg-surface-subtle"
          @click="openDetail(payable)"
        >
          <span class="truncate text-sm font-medium text-txt-primary">{{ payable.supplier_name ?? '—' }}</span>
          <span class="truncate text-sm text-txt-secondary">{{ payable.description }}</span>
          <span class="text-right text-sm font-bold text-txt-primary">{{ formatAmount(payable.total_amount) }}</span>
          <span class="text-sm text-txt-secondary">{{ payable.installments.filter((i) => i.status === 'paid').length }}/{{ payable.installments_count }}</span>
          <span class="flex items-center gap-1.5">
            <StatusBadge :label="payable.status_label" :tone="payable.status === 'paid' ? 'success' : 'neutral'" />
            <StatusBadge v-if="payable.has_overdue_installment" label="Vencida" tone="danger" />
          </span>
        </button>
      </div>
    </div>

    <!-- FORM VIEW -->
    <div v-else-if="view === 'form'" class="space-y-5">
      <div class="flex items-start gap-3.5">
        <IconButton :icon="ArrowLeft" label="Voltar" @click="view = 'list'" />
        <div>
          <h1 class="font-display text-2xl font-extrabold text-txt-primary">Nova conta a pagar</h1>
          <p class="text-sm text-txt-secondary">Informe o fornecedor, o valor total e como ele será parcelado.</p>
        </div>
      </div>

      <form class="space-y-5" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-2 gap-4 rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
          <BaseSelect
            v-model="supplierId"
            label="Fornecedor"
            :options="suppliers.map((s) => ({ value: s.id, label: supplierLabel(s) }))"
            :error="firstFieldError(error, 'supplier_id')"
          />
          <BaseInput v-model="description" label="Descrição / origem" placeholder="Ex.: NF-e 1234" :error="firstFieldError(error, 'description')" />
          <BaseInput
            :model-value="totalMasked"
            label="Valor total"
            :error="firstFieldError(error, 'total_amount')"
            @update:model-value="totalMasked = maskInput($event)"
          />
          <BaseInput v-model.number="installmentsCount" type="number" min="1" label="Número de parcelas" />
          <BaseInput v-model="firstDueDate" type="date" label="Primeiro vencimento" />
          <div class="flex items-end">
            <BaseButton type="button" variant="ghost" :block="false" @click="generateRows">Gerar parcelas</BaseButton>
          </div>
        </div>

        <div v-if="rows.length > 0" class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
          <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-600">
              <Wallet :size="18" />
            </span>
            <p class="font-display text-sm font-bold text-txt-primary">Parcelas (edite se necessário)</p>
          </div>

          <div v-for="row in rows" :key="row.number" class="mb-3 grid grid-cols-[0.5fr_1.5fr_1.5fr] items-end gap-3 last:mb-0">
            <span class="text-sm font-semibold text-txt-secondary">{{ row.number }}ª</span>
            <BaseInput :model-value="row.amountMasked" label="Valor" @update:model-value="row.amountMasked = maskInput($event)" />
            <BaseInput v-model="row.due_date" type="date" label="Vencimento" />
          </div>

          <p class="mt-3 text-sm font-semibold" :class="sumMatches ? 'text-emerald-700' : 'text-rose-600'">
            Soma das parcelas: {{ formatAmount(rowsSum) }} / Total: {{ formatAmount(totalValue) }}
            <span v-if="sumMatches"> — Ok</span>
          </p>
        </div>

        <p v-if="error && !firstFieldError(error, 'supplier_id') && !firstFieldError(error, 'description') && !firstFieldError(error, 'total_amount')" class="text-sm text-rose-600">
          {{ parse(error).message }}
        </p>

        <div class="flex justify-end gap-3">
          <BaseButton type="button" variant="ghost" :block="false" @click="view = 'list'">Cancelar</BaseButton>
          <BaseButton type="submit" :disabled="!canSubmit" :loading="saving" :block="false">Salvar conta a pagar</BaseButton>
        </div>
      </form>
    </div>

    <!-- DETAIL VIEW -->
    <div v-else-if="selected" class="space-y-5">
      <div class="flex items-start gap-3.5">
        <IconButton :icon="ArrowLeft" label="Voltar" @click="view = 'list'" />
        <div>
          <h1 class="font-display text-2xl font-extrabold text-txt-primary">{{ selected.supplier_name }}</h1>
          <p class="text-sm text-txt-secondary">{{ selected.description }}</p>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Valor total</p>
          <p class="mt-1 font-display text-xl font-bold text-txt-primary">{{ formatAmount(selected.total_amount) }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Status</p>
          <StatusBadge class="mt-1.5" :label="selected.status_label" :tone="selected.status === 'paid' ? 'success' : 'neutral'" />
        </div>
        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Parcelas quitadas</p>
          <p class="mt-1 font-display text-xl font-bold text-txt-primary">{{ selected.installments.filter((i) => i.status === 'paid').length }}/{{ selected.installments_count }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
        <div class="grid grid-cols-[0.6fr_1.2fr_1.2fr_1fr_1fr] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
          <span>Parcela</span>
          <span class="text-right">Valor</span>
          <span>Vencimento</span>
          <span>Status</span>
          <span />
        </div>
        <div
          v-for="installment in selected.installments"
          :key="installment.id"
          class="grid grid-cols-[0.6fr_1.2fr_1.2fr_1fr_1fr] items-center gap-2 border-b border-border px-5 py-3 last:border-0"
        >
          <span class="text-sm font-semibold text-txt-secondary">{{ installment.number }}ª</span>
          <span class="text-right text-sm font-bold text-txt-primary">{{ formatAmount(installment.amount) }}</span>
          <span class="text-sm text-txt-secondary">{{ formatDate(installment.due_date) }}</span>
          <span class="flex items-center gap-1.5">
            <StatusBadge :label="installment.status_label" :tone="installment.status === 'paid' ? 'success' : 'neutral'" />
            <StatusBadge v-if="installment.is_overdue" label="Vencida" tone="danger" />
          </span>
          <span class="text-right">
            <BaseButton
              v-if="installment.status === 'pending'"
              :block="false"
              variant="ghost"
              :loading="settlingId === installment.id"
              @click="handleSettle(installment)"
            >
              <CheckCircle2 :size="15" />
              Dar baixa
            </BaseButton>
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
