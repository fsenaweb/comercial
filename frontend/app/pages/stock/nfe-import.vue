<script setup lang="ts">
import { AlertTriangle, ArrowLeft, CheckCircle2, FileUp, Search, Upload } from 'lucide-vue-next'

interface Variation {
  id: number
  product_code: string
  current_quantity: number
  markup: string | null
}

interface Product {
  id: number
  name: string
  variations?: Variation[]
}

interface SkuOption {
  key: string
  productName: string
  variation: Variation
}

interface Supplier {
  id: number
  corporate_name: string
  trade_name: string | null
}

interface ParsedItem {
  product_code: string
  ean: string | null
  description: string
  quantity: number
  unit_cost: number
  total: number
  matched_variation: { id: number, product_name: string, product_code: string, markup: string | null } | null
}

interface ParsedDuplicata {
  number: string
  due_date: string
  amount: number
}

interface ParsedNfe {
  nfe_key: string | null
  nfe_number: string
  nfe_series: string
  issue_date: string | null
  emit: { cnpj: string, name: string }
  items: ParsedItem[]
  freight_value: number
  products_total: number
  total_value: number
  duplicatas: ParsedDuplicata[]
  matched_supplier: { id: number, name: string } | null
}

interface WorkingItem {
  parsed: ParsedItem
  variationId: number | null
  variationLabel: string | null
  updateCost: boolean
}

const api = useApi()
const productsApi = useResourceApi<Product>('products')
const { parse, firstFieldError } = useApiError()

const products = ref<Product[]>([])
const suppliers = ref<Supplier[]>([])

const skuOptions = computed<SkuOption[]>(() => {
  const rows: SkuOption[] = []
  for (const product of products.value) {
    for (const variation of product.variations ?? []) {
      rows.push({ key: `${product.id}-${variation.id}`, productName: product.name, variation })
    }
  }
  return rows
})

onMounted(async () => {
  const [productsResult, suppliersRes] = await Promise.all([
    productsApi.list(),
    api<{ data: Supplier[] }>('/suppliers'),
  ])
  products.value = productsResult
  suppliers.value = suppliersRes.data
})

function supplierLabel(supplier: Supplier): string {
  return supplier.trade_name ?? supplier.corporate_name
}

function formatAmount(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

// ---- STEP 1: upload ----
const step = ref<'upload' | 'review' | 'done'>('upload')
const xmlFile = ref<File | null>(null)
const parsing = ref(false)
const parseError = ref<unknown>(null)
const parsed = ref<ParsedNfe | null>(null)

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  xmlFile.value = input.files?.[0] ?? null
}

const supplierId = ref<number | null>(null)
const workingItems = ref<WorkingItem[]>([])
const generatePayable = ref(false)
const payableDueDate = ref('')

async function handleParse() {
  if (!xmlFile.value) return
  parsing.value = true
  parseError.value = null
  try {
    const formData = new FormData()
    formData.append('xml', xmlFile.value)
    const { data } = await api<{ data: ParsedNfe }>('/stock-entries/parse-xml', {
      method: 'POST',
      body: formData,
    })
    parsed.value = data
    supplierId.value = data.matched_supplier?.id ?? null
    workingItems.value = data.items.map((item) => ({
      parsed: item,
      variationId: item.matched_variation?.id ?? null,
      variationLabel: item.matched_variation ? `${item.matched_variation.product_name} (Cód. ${item.matched_variation.product_code})` : null,
      updateCost: false,
    }))
    generatePayable.value = false
    payableDueDate.value = new Date().toISOString().slice(0, 10)
    step.value = 'review'
  } catch (err) {
    parseError.value = err
  } finally {
    parsing.value = false
  }
}

// ---- MODAL: escolher produto pra item não casado ----
const showPicker = ref(false)
const pickerSearch = ref('')
const pickerTarget = ref<WorkingItem | null>(null)

const filteredPickerRows = computed(() => {
  const q = pickerSearch.value.trim().toLowerCase()
  if (!q) return skuOptions.value
  return skuOptions.value.filter((row) => row.productName.toLowerCase().includes(q) || row.variation.product_code.toLowerCase().includes(q))
})

function openPicker(item: WorkingItem) {
  pickerTarget.value = item
  pickerSearch.value = ''
  showPicker.value = true
}

function choosePickerRow(row: SkuOption) {
  if (pickerTarget.value) {
    pickerTarget.value.variationId = row.variation.id
    pickerTarget.value.variationLabel = `${row.productName} (Cód. ${row.variation.product_code})`
  }
  showPicker.value = false
}

function newSalePricePreview(item: WorkingItem): string | null {
  const markup = item.parsed.matched_variation?.markup
  if (!item.updateCost || markup === null || markup === undefined) return null
  const salePrice = item.parsed.unit_cost * (1 + Number(markup) / 100)
  return formatAmount(salePrice)
}

const allItemsResolved = computed(() => workingItems.value.length > 0 && workingItems.value.every((item) => item.variationId !== null))
const canGeneratePayable = computed(() => supplierId.value !== null)

const payableInstallments = computed(() => {
  if (!parsed.value) return []
  if (parsed.value.duplicatas.length > 0) {
    return parsed.value.duplicatas.map((dup, index) => ({ number: index + 1, amount: dup.amount, due_date: dup.due_date }))
  }
  return [{ number: 1, amount: parsed.value.total_value, due_date: payableDueDate.value }]
})

const canConfirm = computed(() => {
  if (!allItemsResolved.value) return false
  if (generatePayable.value && !canGeneratePayable.value) return false
  if (generatePayable.value && parsed.value?.duplicatas.length === 0 && !payableDueDate.value) return false
  return true
})

// ---- STEP 2 -> CONFIRM ----
const confirming = ref(false)
const confirmError = ref<unknown>(null)

async function handleConfirm() {
  if (!parsed.value || !xmlFile.value) return
  confirming.value = true
  confirmError.value = null
  try {
    const formData = new FormData()
    formData.append('xml', xmlFile.value)
    if (supplierId.value) formData.append('supplier_id', String(supplierId.value))
    const supplier = suppliers.value.find((s) => s.id === supplierId.value)
    if (supplier) formData.append('supplier_name', supplierLabel(supplier))
    formData.append('nfe_number', parsed.value.nfe_number)
    formData.append('nfe_series', parsed.value.nfe_series)
    if (parsed.value.nfe_key) formData.append('nfe_key', parsed.value.nfe_key)
    if (parsed.value.issue_date) formData.append('issue_date', parsed.value.issue_date)
    formData.append('freight_value', String(parsed.value.freight_value))
    formData.append('products_total', String(parsed.value.products_total))
    formData.append('total_value', String(parsed.value.total_value))

    workingItems.value.forEach((item, index) => {
      formData.append(`items[${index}][product_variation_id]`, String(item.variationId))
      formData.append(`items[${index}][quantity]`, String(item.parsed.quantity))
      formData.append(`items[${index}][unit_cost]`, String(item.parsed.unit_cost))
      formData.append(`items[${index}][update_cost]`, item.updateCost ? '1' : '0')
    })

    formData.append('generate_accounts_payable', generatePayable.value ? '1' : '0')
    if (generatePayable.value) {
      payableInstallments.value.forEach((installment, index) => {
        formData.append(`payable_installments[${index}][number]`, String(installment.number))
        formData.append(`payable_installments[${index}][amount]`, String(installment.amount))
        formData.append(`payable_installments[${index}][due_date]`, installment.due_date)
      })
    }

    await api('/stock-entries', { method: 'POST', body: formData })
    step.value = 'done'
  } catch (err) {
    confirmError.value = err
  } finally {
    confirming.value = false
  }
}

function startOver() {
  xmlFile.value = null
  parsed.value = null
  workingItems.value = []
  step.value = 'upload'
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-start gap-3.5">
      <IconButton :icon="ArrowLeft" label="Voltar" @click="navigateTo('/stock/entries')" />
      <div>
        <h1 class="font-display text-2xl font-extrabold text-txt-primary">Importar XML de NF-e</h1>
        <p class="text-sm text-txt-secondary">Envie o XML da nota do fornecedor, confira os itens e confirme a entrada de estoque.</p>
      </div>
    </div>

    <!-- STEP: UPLOAD -->
    <div v-if="step === 'upload'" class="rounded-2xl border border-border bg-surface-raised p-8 shadow-card">
      <div class="flex flex-col items-center gap-4 py-8 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
          <FileUp :size="24" />
        </span>
        <div>
          <p class="font-display text-sm font-bold text-txt-primary">Selecione o arquivo XML da NF-e</p>
          <p class="mt-1 text-xs text-txt-muted">O sistema lê o fornecedor, os itens e os totais direto do XML.</p>
        </div>
        <input type="file" accept=".xml" class="text-sm" @change="handleFileChange">
        <p v-if="firstFieldError(parseError, 'xml')" class="text-sm text-rose-600">{{ firstFieldError(parseError, 'xml') }}</p>
        <p v-else-if="parseError" class="text-sm text-rose-600">{{ parse(parseError).message }}</p>
        <BaseButton :disabled="!xmlFile" :loading="parsing" :block="false" @click="handleParse">
          <Upload :size="15" />
          Analisar XML
        </BaseButton>
      </div>
    </div>

    <!-- STEP: REVIEW -->
    <div v-else-if="step === 'review' && parsed" class="space-y-5">
      <div class="grid grid-cols-3 gap-4">
        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">NF-e</p>
          <p class="mt-1 font-display text-lg font-bold text-txt-primary">{{ parsed.nfe_number }}/{{ parsed.nfe_series }}</p>
          <p class="text-xs text-txt-muted">{{ parsed.emit.name }} · CNPJ {{ parsed.emit.cnpj }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Total dos produtos</p>
          <p class="mt-1 font-display text-lg font-bold text-txt-primary">{{ formatAmount(parsed.products_total) }}</p>
          <p class="text-xs text-txt-muted">Frete {{ formatAmount(parsed.freight_value) }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Total da nota</p>
          <p class="mt-1 font-display text-lg font-bold text-txt-primary">{{ formatAmount(parsed.total_value) }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <p class="mb-3 font-display text-sm font-bold text-txt-primary">Fornecedor</p>
        <BaseSelect
          v-model="supplierId"
          label="Fornecedor no sistema"
          :options="suppliers.map((s) => ({ value: s.id, label: supplierLabel(s) }))"
          :placeholder="parsed.matched_supplier ? undefined : 'Não encontrado pelo CNPJ — selecione'"
        />
        <p v-if="!parsed.matched_supplier" class="mt-2 flex items-center gap-1.5 text-xs text-sky-700">
          <AlertTriangle :size="13" class="shrink-0" />
          Fornecedor não encontrado pelo CNPJ {{ parsed.emit.cnpj }} — selecione o correspondente ou cadastre-o antes de confirmar.
        </p>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <p class="mb-4 font-display text-sm font-bold text-txt-primary">Itens da nota</p>

        <div v-for="(item, index) in workingItems" :key="index" class="mb-4 rounded-xl border border-border p-4 last:mb-0">
          <div class="mb-2 flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-txt-primary">{{ item.parsed.description }}</p>
              <p class="text-[11px] text-txt-muted">Cód. fornecedor {{ item.parsed.product_code }} · EAN {{ item.parsed.ean ?? '—' }} · Qtd. {{ item.parsed.quantity }} · Custo unit. {{ formatAmount(item.parsed.unit_cost) }}</p>
            </div>
          </div>

          <div v-if="item.variationId" class="flex items-center justify-between rounded-xl border border-border bg-surface px-3 py-2">
            <span class="min-w-0 truncate text-sm font-medium text-txt-primary">{{ item.variationLabel }}</span>
            <button type="button" class="shrink-0 cursor-pointer text-xs font-semibold text-brand" @click="openPicker(item)">Trocar</button>
          </div>
          <button
            v-else
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 rounded-xl border border-dashed border-rose-300 bg-rose-50 px-3 py-2 text-left text-rose-700"
            @click="openPicker(item)"
          >
            <Search :size="15" />
            <span class="flex-1 text-sm">Produto não encontrado — clique para buscar ou selecionar</span>
          </button>

          <label class="mt-3 flex items-center gap-2 text-sm text-txt-secondary">
            <input v-model="item.updateCost" type="checkbox" :disabled="!item.variationId">
            Atualizar custo deste item
          </label>
          <p v-if="newSalePricePreview(item)" class="mt-1 text-[11px] text-emerald-700">
            Novo preço de venda (via margem cadastrada): {{ newSalePricePreview(item) }}
          </p>
        </div>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-display text-sm font-bold text-txt-primary">Gerar conta a pagar</p>
            <p class="text-xs text-txt-muted">Cria uma conta a pagar pro fornecedor com o valor total desta nota.</p>
          </div>
          <BaseSwitch v-model="generatePayable" />
        </div>

        <div v-if="generatePayable" class="mt-4 space-y-3">
          <div v-if="parsed.duplicatas.length > 0" class="space-y-1.5">
            <p class="text-[11px] font-bold tracking-wide text-txt-muted uppercase">Parcelas (conforme a nota)</p>
            <div v-for="dup in parsed.duplicatas" :key="dup.number" class="flex justify-between text-sm text-txt-secondary">
              <span>Parcela {{ dup.number }} — vence {{ dup.due_date }}</span>
              <span class="font-semibold text-txt-primary">{{ formatAmount(dup.amount) }}</span>
            </div>
          </div>
          <BaseInput v-else v-model="payableDueDate" type="date" label="Vencimento" />
          <p v-if="!canGeneratePayable" class="text-xs text-rose-600">Selecione o fornecedor para gerar a conta a pagar.</p>
        </div>
      </div>

      <p v-if="confirmError" class="text-sm text-rose-600">{{ parse(confirmError).message }}</p>

      <div class="flex justify-end gap-3">
        <BaseButton type="button" variant="ghost" :block="false" @click="startOver">Cancelar</BaseButton>
        <BaseButton type="button" :disabled="!canConfirm" :loading="confirming" :block="false" @click="handleConfirm">
          <CheckCircle2 :size="15" />
          Confirmar importação
        </BaseButton>
      </div>
    </div>

    <!-- STEP: DONE -->
    <div v-else-if="step === 'done'" class="rounded-2xl border border-border bg-surface-raised p-8 text-center shadow-card">
      <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
        <CheckCircle2 :size="24" />
      </span>
      <p class="mt-4 font-display text-lg font-bold text-txt-primary">Entrada importada com sucesso</p>
      <p class="mt-1 text-sm text-txt-secondary">O estoque dos itens da nota já foi atualizado.</p>
      <div class="mt-5 flex justify-center gap-3">
        <BaseButton variant="ghost" :block="false" @click="startOver">Importar outra nota</BaseButton>
        <BaseButton :block="false" @click="navigateTo('/stock/entries')">Ver entradas de estoque</BaseButton>
      </div>
    </div>

    <!-- MODAL: BUSCAR PRODUTO -->
    <BaseModal :open="showPicker" size="lg" title="Buscar produto" subtitle="Busque por nome ou código e escolha o item correspondente." @close="showPicker = false">
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
          class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 hover:bg-surface-subtle"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-bold text-txt-primary">{{ row.productName }}</p>
            <p class="text-[11.5px] text-txt-muted">Cód. {{ row.variation.product_code }} · {{ row.variation.current_quantity }} em estoque</p>
          </div>
          <BaseButton :block="false" @click="choosePickerRow(row)">Escolher</BaseButton>
        </div>
        <p v-if="filteredPickerRows.length === 0" class="py-6 text-center text-sm text-txt-muted">Nenhum produto encontrado.</p>
      </div>
    </BaseModal>
  </div>
</template>
