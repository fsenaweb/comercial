<script setup lang="ts">
import { ArrowLeft, CheckCircle2, Edit2, Plus, Search, ShoppingBag, Trash2 } from 'lucide-vue-next'
import { lineTotalCents, saleTotalCents, subtotalCents, type DiscountType } from '~/utils/cartMath'
import type { ProductVariationRow } from '~/composables/useProductVariationSearch'

interface Customer {
  id: number
  name: string
}

interface PaymentMethod {
  id: number
  name: string
}

interface AccountEntryItem {
  id: number
  product_variation_id: number
  product_name: string | null
  product_code: string | null
  quantity: number
  unit_price: string
  discount_type: DiscountType
  discount_value: string
  discount: string
  total: string
}

interface AccountEntry {
  id: number
  type: 'purchase' | 'payment'
  type_label: string
  subtotal: string | null
  discount_type: DiscountType | null
  discount_value: string | null
  discount: string | null
  amount: string
  description: string
  payment_method_id: number | null
  payment_method_name: string | null
  items: AccountEntryItem[]
  created_by_name: string | null
  created_at: string
}

interface AccountsReceivable {
  id: number
  customer_id: number
  customer_name: string | null
  balance: string
  last_entry_at: string | null
  entries: AccountEntry[]
  notes: string | null
}

type SkuOption = ProductVariationRow

interface FormItem {
  key: number
  selected: SkuOption | null
  itemId: number | null // preenchido só ao editar uma compra existente
  productLabel: string | null // nome/código exibido ao editar (produto já fixo, sem SkuOption)
  quantity: number
  unitPriceMasked: string
  discountType: DiscountType
  discountValue: number
  locked: boolean // true ao editar: produto/quantidade não podem mudar
}

const api = useApi()
const { search: searchProductVariations } = useProductVariationSearch()
const { parse, firstFieldError } = useApiError()
const { maskInput, toNumber, format } = useCurrencyMask()

const view = ref<'list' | 'detail'>('list')
const loading = ref(true)
const accounts = ref<AccountsReceivable[]>([])
const customers = ref<Customer[]>([])
const paymentMethods = ref<PaymentMethod[]>([])
const statusFilter = ref<'all' | 'open' | 'paid'>('open')

async function loadAll() {
  loading.value = true
  const [accountsRes, customersRes, paymentMethodsRes] = await Promise.all([
    api<{ data: AccountsReceivable[] }>('/accounts-receivable'),
    api<{ data: Customer[] }>('/customers'),
    api<{ data: PaymentMethod[] }>('/payment-methods'),
  ])
  accounts.value = accountsRes.data
  customers.value = customersRes.data
  paymentMethods.value = paymentMethodsRes.data
  loading.value = false
}

const filteredAccounts = computed(() => {
  if (statusFilter.value === 'all') return accounts.value
  if (statusFilter.value === 'open') return accounts.value.filter((a) => Number(a.balance) > 0)
  return accounts.value.filter((a) => Number(a.balance) <= 0)
})

function formatAmount(value: string | number): string {
  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ---- Formulário de itens compartilhado entre "Lançar compra" e "Editar compra" ----
let itemKeySeq = 0
function emptyFormItem(): FormItem {
  itemKeySeq += 1
  return { key: itemKeySeq, selected: null, itemId: null, productLabel: null, quantity: 1, unitPriceMasked: 'R$ 0,00', discountType: 'fixed', discountValue: 0, locked: false }
}

const formItems = ref<FormItem[]>([emptyFormItem()])
const formDiscountType = ref<DiscountType>('fixed')
const formDiscountValue = ref(0)

function addFormItem() {
  formItems.value.push(emptyFormItem())
}

function removeFormItem(key: number) {
  formItems.value = formItems.value.filter((item) => item.key !== key)
  if (formItems.value.length === 0) formItems.value.push(emptyFormItem())
}

function cartLine(item: FormItem) {
  return {
    unitPrice: toNumber(item.unitPriceMasked),
    quantity: item.quantity,
    discountType: item.discountType,
    discountValue: item.discountValue,
  }
}

const formValidItems = computed(() => formItems.value.filter((item) => item.selected || item.locked))
const formSubtotalCents = computed(() => subtotalCents(formValidItems.value.map(cartLine)))
const formTotalCents = computed(() => saleTotalCents(formSubtotalCents.value, formDiscountType.value, formDiscountValue.value))

function itemLineTotal(item: FormItem): number {
  return lineTotalCents(cartLine(item))
}

// ---- Modal de busca de produto (F2), mesmo padrão do PDV/Entradas de Estoque
// - busca no banco (debounced). Antes filtrava uma lista fixa carregada no
// mount (só a primeira página de `GET /products`, que virou paginado -
// achado do cliente, 2026-07-21: a busca não encontrava produto fora dela).
const showPicker = ref(false)
const pickerSearch = ref('')
const pickerTarget = ref<FormItem | null>(null)
const filteredPickerRows = ref<SkuOption[]>([])
let pickerDebounce: ReturnType<typeof setTimeout> | null = null

watch(pickerSearch, (query) => {
  if (pickerDebounce) clearTimeout(pickerDebounce)
  if (!query.trim()) {
    filteredPickerRows.value = []
    return
  }
  pickerDebounce = setTimeout(async () => {
    filteredPickerRows.value = await searchProductVariations(query, 20)
  }, 200)
})

function openPicker(item: FormItem) {
  if (item.locked) return
  pickerTarget.value = item
  pickerSearch.value = ''
  filteredPickerRows.value = []
  showPicker.value = true
}

function choosePickerRow(row: SkuOption) {
  if (pickerTarget.value) {
    pickerTarget.value.selected = row
    pickerTarget.value.unitPriceMasked = format(Math.round(Number(row.variation.sale_price) * 100))
  }
  showPicker.value = false
}

function handleGlobalKeydown(event: KeyboardEvent) {
  if (event.key !== 'F2' || !showDebitModal.value) return
  event.preventDefault()
  const target = pickerTarget.value ?? formItems.value.find((item) => !item.selected && !item.locked) ?? formItems.value[formItems.value.length - 1]
  if (target && !target.locked) openPicker(target)
}

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown))

// ---- MODAL: lançar compra ----
const showDebitModal = ref(false)
const debitCustomerId = ref<number | null>(null)
const debitDescription = ref('')
const debitSaving = ref(false)
const debitError = ref<unknown>(null)

function openDebitModal() {
  debitCustomerId.value = null
  debitDescription.value = ''
  formItems.value = [emptyFormItem()]
  formDiscountType.value = 'fixed'
  formDiscountValue.value = 0
  debitError.value = null
  showDebitModal.value = true
}

const canSubmitDebit = computed(() =>
  debitCustomerId.value !== null
  && debitDescription.value.trim().length > 0
  && formItems.value.some((item) => item.selected && item.quantity > 0),
)

async function handleSubmitDebit() {
  debitSaving.value = true
  debitError.value = null
  try {
    await api('/accounts-receivable/debits', {
      method: 'POST',
      body: {
        customer_id: debitCustomerId.value,
        description: debitDescription.value,
        discount_type: formDiscountType.value,
        discount_value: formDiscountValue.value,
        items: formItems.value.filter((item) => item.selected && item.quantity > 0).map((item) => ({
          product_variation_id: item.selected!.variation.id,
          quantity: item.quantity,
          unit_price: toNumber(item.unitPriceMasked),
          discount_type: item.discountType,
          discount_value: item.discountValue,
        })),
      },
    })
    showDebitModal.value = false
    await loadAll()
  } catch (err) {
    debitError.value = err
  } finally {
    debitSaving.value = false
  }
}

// ---- DETAIL ----
const selected = ref<AccountsReceivable | null>(null)

async function openDetail(account: AccountsReceivable) {
  const { data } = await api<{ data: AccountsReceivable }>(`/accounts-receivable/${account.id}`)
  selected.value = data
  view.value = 'detail'
}

// ---- MODAL: editar compra (revisão de preço/desconto no pagamento) ----
const showEditModal = ref(false)
const editingEntry = ref<AccountEntry | null>(null)
const editSaving = ref(false)
const editError = ref<unknown>(null)

function openEditModal(entry: AccountEntry) {
  editingEntry.value = entry
  formItems.value = entry.items.map((item) => {
    itemKeySeq += 1
    return {
      key: itemKeySeq,
      selected: null,
      itemId: item.id,
      productLabel: `${item.product_name} (Cód. ${item.product_code})`,
      quantity: item.quantity,
      unitPriceMasked: format(Math.round(Number(item.unit_price) * 100)),
      discountType: item.discount_type,
      discountValue: Number(item.discount_value),
      locked: true,
    }
  })
  formDiscountType.value = entry.discount_type ?? 'fixed'
  formDiscountValue.value = Number(entry.discount_value ?? 0)
  editError.value = null
  showEditModal.value = true
}

async function handleSubmitEdit() {
  if (!editingEntry.value) return
  editSaving.value = true
  editError.value = null
  try {
    await api(`/accounts-receivable/debits/${editingEntry.value.id}`, {
      method: 'PUT',
      body: {
        discount_type: formDiscountType.value,
        discount_value: formDiscountValue.value,
        items: formItems.value.map((item) => ({
          id: item.itemId,
          unit_price: toNumber(item.unitPriceMasked),
          discount_type: item.discountType,
          discount_value: item.discountValue,
        })),
      },
    })
    showEditModal.value = false
    if (selected.value) await openDetail(selected.value)
    await loadAll()
  } catch (err) {
    editError.value = err
  } finally {
    editSaving.value = false
  }
}

// ---- MODAL: registrar pagamento ----
const showPaymentModal = ref(false)
const paymentAmountMasked = ref('R$ 0,00')
const paymentMethodId = ref<number | null>(null)
const paymentSaving = ref(false)
const paymentError = ref<unknown>(null)

function openPaymentModal() {
  if (!selected.value) return
  paymentAmountMasked.value = format(Math.round(Number(selected.value.balance) * 100))
  paymentMethodId.value = null
  paymentError.value = null
  showPaymentModal.value = true
}

async function handleSubmitPayment() {
  if (!selected.value) return
  paymentSaving.value = true
  paymentError.value = null
  try {
    await api(`/accounts-receivable/${selected.value.id}/payments`, {
      method: 'POST',
      body: {
        amount: toNumber(paymentAmountMasked.value),
        payment_method_id: paymentMethodId.value,
      },
    })
    showPaymentModal.value = false
    await openDetail(selected.value)
    await loadAll()
  } catch (err) {
    paymentError.value = err
  } finally {
    paymentSaving.value = false
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
          <h1 class="font-display text-[30px] font-extrabold text-brand">Crediário</h1>
          <p class="text-sm text-txt-secondary">Conta corrente por cliente ("caderneta") - compras acumulam durante o mês, pagamento total ou parcial a qualquer momento. Lançamento manual, fora do PDV.</p>
        </div>
        <BaseButton :block="false" @click="openDebitModal">
          <Plus :size="15" />
          Lançar compra
        </BaseButton>
      </div>

      <div class="flex gap-2">
        <button
          v-for="option in [{ value: 'open', label: 'Com saldo' }, { value: 'paid', label: 'Quitados' }, { value: 'all', label: 'Todos' }]"
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
        <div class="grid grid-cols-[2fr_1fr_1.4fr] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
          <span>Cliente</span>
          <span class="text-right">Saldo devedor</span>
          <span>Último lançamento</span>
        </div>

        <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
        <div v-else-if="filteredAccounts.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">Nenhuma conta encontrada.</div>
        <button
          v-for="account in filteredAccounts"
          v-else
          :key="account.id"
          type="button"
          class="cursor-pointer grid w-full grid-cols-[2fr_1fr_1.4fr] items-center gap-2 border-b border-border px-5 py-3 text-left last:border-0 hover:bg-surface-subtle"
          @click="openDetail(account)"
        >
          <span class="truncate text-sm font-medium text-txt-primary">{{ account.customer_name ?? '-' }}</span>
          <span class="text-right text-sm font-bold" :class="Number(account.balance) > 0 ? 'text-txt-primary' : 'text-emerald-700'">
            {{ formatAmount(account.balance) }}
          </span>
          <span class="text-sm text-txt-secondary">{{ account.last_entry_at ? formatDateTime(account.last_entry_at) : '-' }}</span>
        </button>
      </div>
    </div>

    <!-- DETAIL VIEW -->
    <div v-else-if="selected" class="space-y-5">
      <div class="flex items-start gap-3.5">
        <IconButton :icon="ArrowLeft" label="Voltar" @click="view = 'list'" />
        <div class="flex-1">
          <h1 class="font-display text-2xl font-extrabold text-txt-primary">{{ selected.customer_name }}</h1>
          <p class="text-sm text-txt-secondary">Extrato da conta corrente.</p>
        </div>
        <BaseButton v-if="Number(selected.balance) > 0" :block="false" @click="openPaymentModal">
          <CheckCircle2 :size="15" />
          Registrar pagamento
        </BaseButton>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
        <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Saldo devedor atual</p>
        <p class="mt-1 font-display text-2xl font-bold" :class="Number(selected.balance) > 0 ? 'text-txt-primary' : 'text-emerald-700'">
          {{ formatAmount(selected.balance) }}
        </p>
      </div>

      <div class="space-y-3">
        <div v-if="selected.entries.length === 0" class="rounded-2xl border border-dashed border-border bg-surface-raised px-5 py-11 text-center text-sm text-txt-muted">
          Nenhum lançamento ainda.
        </div>
        <div v-for="entry in selected.entries" :key="entry.id" class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
              <StatusBadge :label="entry.type_label" :tone="entry.type === 'payment' ? 'success' : 'neutral'" />
              <div class="min-w-0">
                <p class="text-sm font-medium text-txt-primary">{{ entry.description }}</p>
                <p class="text-[11px] text-txt-muted">
                  {{ formatDateTime(entry.created_at) }}
                  <span v-if="entry.payment_method_name"> · {{ entry.payment_method_name }}</span>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold" :class="entry.type === 'payment' ? 'text-emerald-700' : 'text-txt-primary'">
                {{ entry.type === 'payment' ? '−' : '+' }}{{ formatAmount(entry.amount) }}
              </span>
              <IconButton v-if="entry.type === 'purchase'" :icon="Edit2" label="Editar compra (revisar preço/desconto)" @click="openEditModal(entry)" />
            </div>
          </div>

          <div v-if="entry.items.length > 0" class="mt-3 space-y-1 border-t border-border pt-3">
            <div v-for="item in entry.items" :key="item.id" class="flex items-center justify-between gap-3 text-xs text-txt-secondary">
              <span class="truncate">{{ item.quantity }}x {{ item.product_name }} <span class="text-txt-muted">(Cód. {{ item.product_code }})</span></span>
              <span class="shrink-0 font-medium text-txt-primary">{{ formatAmount(item.total) }}</span>
            </div>
            <p v-if="entry.discount && Number(entry.discount) > 0" class="text-right text-[11px] text-emerald-700">Desconto geral: −{{ formatAmount(entry.discount) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: LANÇAR COMPRA -->
    <BaseModal :open="showDebitModal" size="lg" title="Lançar compra" subtitle="Registra os itens levados pelo cliente - dá baixa no estoque, sem mexer no caixa." @close="showDebitModal = false">
      <form class="space-y-5" @submit.prevent="handleSubmitDebit">
        <div class="grid grid-cols-2 gap-4">
          <BaseSelect
            v-model="debitCustomerId"
            label="Cliente"
            :options="customers.map((c) => ({ value: c.id, label: c.name }))"
            :error="firstFieldError(debitError, 'customer_id')"
          />
          <BaseInput v-model="debitDescription" label="Descrição" placeholder="Ex.: Peças balcão 05/07" :error="firstFieldError(debitError, 'description')" />
        </div>

        <div>
          <div class="mb-3 flex items-center justify-between">
            <p class="font-display text-sm font-bold text-txt-primary">Produtos</p>
            <BaseButton type="button" variant="ghost" :block="false" @click="addFormItem">
              <Plus :size="15" />
              Adicionar item
            </BaseButton>
          </div>

          <div v-for="(item, index) in formItems" :key="item.key" class="mb-3 rounded-xl border border-border p-4 last:mb-0">
            <div class="mb-2 flex items-center justify-between">
              <p class="text-xs font-bold text-txt-primary">Item {{ index + 1 }}</p>
              <IconButton v-if="formItems.length > 1" :icon="Trash2" label="Remover item" tone="danger" @click="removeFormItem(item.key)" />
            </div>

            <button
              v-if="!item.selected"
              type="button"
              class="flex w-full cursor-pointer items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-left text-txt-muted hover:border-brand"
              @click="openPicker(item)"
            >
              <Search :size="15" />
              <span class="flex-1 text-sm">Clique ou tecle <strong class="text-txt-secondary">F2</strong> para buscar produto...</span>
            </button>

            <div v-else class="space-y-3">
              <div class="flex items-center justify-between rounded-xl border border-border bg-surface px-3 py-2">
                <span class="min-w-0 truncate text-sm font-medium text-txt-primary">{{ item.selected.productName }} <span class="text-[11px] text-txt-muted">(Cód. {{ item.selected.variation.code }})</span></span>
                <button type="button" class="shrink-0 cursor-pointer text-xs font-semibold text-brand" @click="item.selected = null">Trocar</button>
              </div>
              <div class="grid grid-cols-[1fr_1.3fr_1.3fr] gap-3">
                <BaseInput v-model.number="item.quantity" type="number" min="1" label="Quantidade" />
                <BaseInput :model-value="item.unitPriceMasked" label="Preço unitário" @update:model-value="item.unitPriceMasked = maskInput($event)" />
                <div>
                  <span class="mb-1 block text-sm font-medium text-txt-secondary">Desconto do item</span>
                  <DiscountInput :type="item.discountType" :value="item.discountValue" @update:type="item.discountType = $event" @update:value="item.discountValue = $event" />
                </div>
              </div>
              <p class="text-right text-xs font-semibold text-txt-secondary">Total do item: {{ formatAmount(itemLineTotal(item) / 100) }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-border p-4">
          <div class="flex items-center justify-between gap-4">
            <span class="text-sm font-medium text-txt-secondary">Desconto geral</span>
            <DiscountInput :type="formDiscountType" :value="formDiscountValue" @update:type="formDiscountType = $event" @update:value="formDiscountValue = $event" />
          </div>
          <div class="mt-3 flex justify-between border-t border-border pt-3 text-sm">
            <span class="text-txt-secondary">Subtotal</span>
            <span class="font-medium text-txt-primary">{{ formatAmount(formSubtotalCents / 100) }}</span>
          </div>
          <div class="flex justify-between text-base font-bold">
            <span class="text-txt-primary">Total da compra</span>
            <span class="text-txt-primary">{{ formatAmount(formTotalCents / 100) }}</span>
          </div>
        </div>

        <p v-if="debitError && !firstFieldError(debitError, 'customer_id') && !firstFieldError(debitError, 'description')" class="text-sm text-rose-600">
          {{ parse(debitError).message }}
        </p>

        <div class="flex justify-end gap-3">
          <BaseButton type="button" variant="ghost" :block="false" @click="showDebitModal = false">Cancelar</BaseButton>
          <BaseButton type="submit" :disabled="!canSubmitDebit" :loading="debitSaving" :block="false">Lançar</BaseButton>
        </div>
      </form>
    </BaseModal>

    <!-- MODAL: EDITAR COMPRA -->
    <BaseModal :open="showEditModal" size="lg" title="Editar compra" subtitle="Revise preço ou aplique desconto - produto e quantidade já foram baixados do estoque e não mudam aqui." @close="showEditModal = false">
      <form class="space-y-5" @submit.prevent="handleSubmitEdit">
        <div v-for="item in formItems" :key="item.key" class="rounded-xl border border-border p-4">
          <div class="mb-3 flex items-center gap-2 text-sm font-medium text-txt-primary">
            <ShoppingBag :size="15" class="text-txt-muted" />
            {{ item.quantity }}x {{ item.productLabel }}
          </div>
          <div class="grid grid-cols-2 gap-3">
            <BaseInput :model-value="item.unitPriceMasked" label="Preço unitário" @update:model-value="item.unitPriceMasked = maskInput($event)" />
            <div>
              <span class="mb-1 block text-sm font-medium text-txt-secondary">Desconto do item</span>
              <DiscountInput :type="item.discountType" :value="item.discountValue" @update:type="item.discountType = $event" @update:value="item.discountValue = $event" />
            </div>
          </div>
          <p class="mt-2 text-right text-xs font-semibold text-txt-secondary">Total do item: {{ formatAmount(itemLineTotal(item) / 100) }}</p>
        </div>

        <div class="rounded-xl border border-border p-4">
          <div class="flex items-center justify-between gap-4">
            <span class="text-sm font-medium text-txt-secondary">Desconto geral</span>
            <DiscountInput :type="formDiscountType" :value="formDiscountValue" @update:type="formDiscountType = $event" @update:value="formDiscountValue = $event" />
          </div>
          <div class="mt-3 flex justify-between border-t border-border pt-3 text-base font-bold">
            <span class="text-txt-primary">Novo total da compra</span>
            <span class="text-txt-primary">{{ formatAmount(formTotalCents / 100) }}</span>
          </div>
        </div>

        <p v-if="editError" class="text-sm text-rose-600">{{ firstFieldError(editError, 'amount') ?? parse(editError).message }}</p>

        <div class="flex justify-end gap-3">
          <BaseButton type="button" variant="ghost" :block="false" @click="showEditModal = false">Cancelar</BaseButton>
          <BaseButton type="submit" :loading="editSaving" :block="false">Salvar revisão</BaseButton>
        </div>
      </form>
    </BaseModal>

    <!-- MODAL: REGISTRAR PAGAMENTO -->
    <BaseModal :open="showPaymentModal" title="Registrar pagamento" :subtitle="selected ? `Saldo devedor: ${formatAmount(selected.balance)}` : ''" @close="showPaymentModal = false">
      <form class="space-y-4" @submit.prevent="handleSubmitPayment">
        <BaseInput
          :model-value="paymentAmountMasked"
          label="Valor pago (total ou parcial)"
          :error="firstFieldError(paymentError, 'amount')"
          @update:model-value="paymentAmountMasked = maskInput($event)"
        />
        <BaseSelect
          v-model="paymentMethodId"
          label="Forma de pagamento"
          :options="paymentMethods.map((m) => ({ value: m.id, label: m.name }))"
          :error="firstFieldError(paymentError, 'payment_method_id')"
        />
        <p v-if="paymentError && !firstFieldError(paymentError, 'amount') && !firstFieldError(paymentError, 'payment_method_id')" class="text-sm text-rose-600">
          {{ parse(paymentError).message }}
        </p>
        <div class="flex justify-end gap-3">
          <BaseButton type="button" variant="ghost" :block="false" @click="showPaymentModal = false">Cancelar</BaseButton>
          <BaseButton type="submit" :disabled="!paymentMethodId" :loading="paymentSaving" :block="false">Confirmar pagamento</BaseButton>
        </div>
      </form>
    </BaseModal>

    <!-- MODAL: BUSCAR PRODUTO (F2) -->
    <BaseModal :open="showPicker" size="lg" title="Buscar produto" subtitle="Busque por nome ou código e escolha o item." @close="showPicker = false">
      <label class="relative mb-3 block">
        <Search :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-txt-muted" />
        <input
          v-model="pickerSearch"
          type="text"
          placeholder="Nome ou código do produto"
          autofocus
          class="w-full rounded-xl border border-border py-2.5 pr-3 pl-9 text-sm"
        >
      </label>
      <div class="max-h-96 space-y-1 overflow-y-auto">
        <div
          v-for="row in filteredPickerRows"
          :key="row.key"
          class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2.5 hover:bg-surface-subtle"
          @click="choosePickerRow(row)"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-bold text-txt-primary">{{ row.productName }}</p>
            <p class="text-[11.5px] text-txt-muted">Cód. {{ row.variation.code }} · {{ row.variation.current_quantity }} em estoque · {{ formatAmount(row.variation.sale_price) }}</p>
          </div>
          <BaseButton :block="false" @click.stop="choosePickerRow(row)">Escolher</BaseButton>
        </div>
        <p v-if="filteredPickerRows.length === 0" class="py-6 text-center text-sm text-txt-muted">Nenhum produto encontrado.</p>
      </div>
    </BaseModal>
  </div>
</template>
