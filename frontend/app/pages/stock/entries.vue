<script setup lang="ts">
import { AlertTriangle, ArrowLeft, FileUp, PackagePlus, Plus, Search, Trash2 } from 'lucide-vue-next'

interface Variation {
  id: number
  product_code: string
  current_quantity: number
  max_quantity: number | null
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

interface StockMovement {
  id: number
  product_name: string | null
  product_code: string | null
  quantity: number
  origin: string
  user_name: string | null
  created_at: string
}

interface FormItem {
  key: number
  selected: SkuOption | null
  quantity: number | null
}

const productsApi = useResourceApi<Product>('products')
const api = useApi()
const { parse, firstFieldError } = useApiError()

const products = ref<Product[]>([])
const entries = ref<StockMovement[]>([])
const loading = ref(true)
const view = ref<'list' | 'form'>('list')

const showOriginModal = ref(false)
const originModalEntry = ref<StockMovement | null>(null)

function openOriginModal(entry: StockMovement) {
  originModalEntry.value = entry
  showOriginModal.value = true
}

const skuOptions = computed<SkuOption[]>(() => {
  const rows: SkuOption[] = []
  for (const product of products.value) {
    for (const variation of product.variations ?? []) {
      rows.push({ key: `${product.id}-${variation.id}`, productName: product.name, variation })
    }
  }
  return rows
})

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function loadEntries() {
  loading.value = true
  const { data } = await api<{ data: StockMovement[] }>('/stock-movements?type=in')
  entries.value = data
  loading.value = false
}

async function loadAll() {
  const [productsResult] = await Promise.all([productsApi.list(), loadEntries()])
  products.value = productsResult
}

let itemKeySeq = 0
function emptyItem(): FormItem {
  itemKeySeq += 1
  return { key: itemKeySeq, selected: null, quantity: null }
}

const origin = ref('')
const items = ref<FormItem[]>([emptyItem()])
const saving = ref(false)
const error = ref<unknown>(null)

function openForm() {
  origin.value = ''
  items.value = [emptyItem()]
  error.value = null
  view.value = 'form'
}

function closeForm() {
  view.value = 'list'
}

function addItem() {
  items.value.push(emptyItem())
}

function removeItem(key: number) {
  items.value = items.value.filter((item) => item.key !== key)
  if (items.value.length === 0) items.value.push(emptyItem())
}

// ---- Modal de busca de produto (F2), mesmo padrão do PDV ----
const showPicker = ref(false)
const pickerSearch = ref('')
const pickerTarget = ref<FormItem | null>(null)

const filteredPickerRows = computed(() => {
  const q = pickerSearch.value.trim().toLowerCase()
  if (!q) return skuOptions.value
  return skuOptions.value.filter((row) => row.productName.toLowerCase().includes(q) || row.variation.product_code.toLowerCase().includes(q))
})

function openPicker(item: FormItem) {
  pickerTarget.value = item
  pickerSearch.value = ''
  showPicker.value = true
}

function choosePickerRow(row: SkuOption) {
  if (pickerTarget.value) pickerTarget.value.selected = row
  showPicker.value = false
}

function handleGlobalKeydown(event: KeyboardEvent) {
  if (event.key !== 'F2' || view.value !== 'form') return
  event.preventDefault()
  const target = pickerTarget.value ?? items.value.find((item) => !item.selected) ?? items.value[items.value.length - 1]
  if (target) openPicker(target)
}

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown))

const canSubmit = computed(() => origin.value.trim().length > 0 && items.value.some((item) => item.selected && item.quantity && item.quantity > 0))

function excessWarning(item: FormItem): string | null {
  if (!item.selected || !item.quantity) return null
  const max = item.selected.variation.max_quantity
  if (max === null) return null
  const resulting = item.selected.variation.current_quantity + item.quantity
  if (resulting <= max) return null
  return `Estoque máximo cadastrado é ${max} — esta entrada deixará o saldo em ${resulting} (acima do limite).`
}

async function handleSubmit() {
  saving.value = true
  error.value = null

  try {
    const validItems = items.value.filter((item) => item.selected && item.quantity && item.quantity > 0)
    for (const item of validItems) {
      await api('/stock-movements/entries', {
        method: 'POST',
        body: {
          product_variation_id: item.selected!.variation.id,
          quantity: item.quantity,
          origin: origin.value,
        },
      })
    }
    closeForm()
    await loadAll()
  } catch (err) {
    error.value = err
  } finally {
    saving.value = false
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
          <h1 class="font-display text-[30px] font-extrabold text-brand">Entradas de Estoque</h1>
          <p class="text-sm text-txt-secondary">Registre o recebimento de mercadoria de fornecedores, aumentando o saldo de produtos já cadastrados.</p>
        </div>
        <div class="flex gap-2.5">
          <BaseButton variant="ghost" :block="false" @click="navigateTo('/stock/nfe-import')">
            <FileUp :size="15" />
            Importar XML de NF-e
          </BaseButton>
          <BaseButton :block="false" @click="openForm">
            <Plus :size="15" />
            Nova entrada
          </BaseButton>
        </div>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
        <div class="grid grid-cols-[1.1fr_1.8fr_0.8fr_1.8fr_1fr] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
          <span>Data</span>
          <span>Produto</span>
          <span class="text-right">Qtde.</span>
          <span>Origem</span>
          <span>Usuário</span>
        </div>

        <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
        <div v-else-if="entries.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">Nenhuma entrada encontrada.</div>
        <div
          v-for="entry in entries"
          v-else
          :key="entry.id"
          class="grid grid-cols-[1.1fr_1.8fr_0.8fr_1.8fr_1fr] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
        >
          <span class="text-sm text-txt-secondary">{{ formatDateTime(entry.created_at) }}</span>
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-txt-primary">{{ entry.product_name ?? '—' }}</p>
            <p class="text-[11px] text-txt-muted">Cód. {{ entry.product_code ?? '—' }}</p>
          </div>
          <span class="text-right text-sm font-bold text-emerald-700">+{{ entry.quantity }}</span>
          <button type="button" class="min-w-0 cursor-pointer truncate text-left text-sm text-txt-secondary hover:text-txt-primary hover:underline" @click="openOriginModal(entry)">
            {{ entry.origin }}
          </button>
          <span class="min-w-0 truncate text-sm text-txt-secondary">{{ entry.user_name ?? '—' }}</span>
        </div>
      </div>
    </div>

    <!-- FORM VIEW -->
    <div v-else class="space-y-5">
      <div class="flex items-start gap-3.5">
        <IconButton :icon="ArrowLeft" label="Voltar" @click="closeForm" />
        <div>
          <h1 class="font-display text-2xl font-extrabold text-txt-primary">Nova entrada</h1>
          <p class="text-sm text-txt-secondary">Busque cada produto já cadastrado, informe a quantidade recebida e o motivo/origem da entrada.</p>
        </div>
      </div>

      <form class="space-y-5" @submit.prevent="handleSubmit">
        <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
          <div class="mb-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                <PackagePlus :size="18" />
              </span>
              <p class="font-display text-sm font-bold text-txt-primary">Produtos recebidos</p>
            </div>
            <BaseButton type="button" variant="ghost" :block="false" @click="addItem">
              <Plus :size="15" />
              Adicionar item
            </BaseButton>
          </div>

          <div v-for="(item, index) in items" :key="item.key" class="mb-4 rounded-xl border border-border p-4 last:mb-0">
            <div class="mb-3 flex items-center justify-between">
              <p class="text-xs font-bold text-txt-primary">Item {{ index + 1 }}</p>
              <IconButton v-if="items.length > 1" :icon="Trash2" label="Remover item" tone="danger" @click="removeItem(item.key)" />
            </div>

            <div v-if="!item.selected">
              <label class="mb-1 block text-sm font-medium text-txt-secondary">Produto</label>
              <button
                type="button"
                class="flex w-full cursor-pointer items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-left text-txt-muted hover:border-brand"
                @click="openPicker(item)"
              >
                <Search :size="15" />
                <span class="flex-1 text-sm">Clique ou tecle <strong class="text-txt-secondary">F2</strong> para buscar produto...</span>
              </button>
            </div>

            <div v-else class="grid grid-cols-[2fr_1fr] items-end gap-4">
              <div>
                <span class="mb-1 block text-sm font-medium text-txt-secondary">Produto</span>
                <div class="flex items-center justify-between rounded-xl border border-border bg-surface px-3 py-2">
                  <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-txt-primary">{{ item.selected.productName }}</span>
                    <span class="block text-[11px] text-txt-muted">Cód. {{ item.selected.variation.product_code }} · {{ item.selected.variation.current_quantity }} em estoque</span>
                  </span>
                  <button type="button" class="shrink-0 cursor-pointer text-xs font-semibold text-brand" @click="item.selected = null">Trocar</button>
                </div>
              </div>
              <BaseInput v-model.number="item.quantity" type="number" label="Quantidade recebida" />
            </div>
            <p v-if="excessWarning(item)" class="mt-2 flex items-center gap-1.5 text-xs text-sky-700">
              <AlertTriangle :size="13" class="shrink-0" />
              {{ excessWarning(item) }}
            </p>
          </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
          <p class="mb-4 font-display text-sm font-bold text-txt-primary">Motivo da entrada</p>
          <BaseInput
            v-model="origin"
            label="Origem / motivo"
            placeholder="Ex.: Compra NF 1234 - Fornecedor XPTO"
            :error="firstFieldError(error, 'origin')"
          />
          <p class="mt-1 text-[11px] text-txt-muted">Descreva a origem (fornecedor, número da nota, devolução de cliente etc.).</p>
        </div>

        <p v-if="error && !firstFieldError(error, 'origin')" class="text-sm text-rose-600">{{ parse(error).message }}</p>

        <div class="flex justify-end gap-3">
          <BaseButton type="button" variant="ghost" :block="false" @click="closeForm">Voltar</BaseButton>
          <BaseButton type="submit" :disabled="!canSubmit" :loading="saving" :block="false">Salvar entrada</BaseButton>
        </div>
      </form>
    </div>

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

    <!-- MODAL: ORIGEM / MOTIVO COMPLETO -->
    <BaseModal :open="showOriginModal" title="Origem / motivo" subtitle="Detalhe completo da entrada." @close="showOriginModal = false">
      <div v-if="originModalEntry" class="space-y-3 text-sm">
        <p class="text-txt-primary">{{ originModalEntry.origin }}</p>
        <div class="grid grid-cols-2 gap-3 border-t border-border pt-3 text-txt-secondary">
          <span>Produto: <strong class="text-txt-primary">{{ originModalEntry.product_name ?? '—' }}</strong></span>
          <span>Data: <strong class="text-txt-primary">{{ formatDateTime(originModalEntry.created_at) }}</strong></span>
          <span>Quantidade: <strong class="text-emerald-700">+{{ originModalEntry.quantity }}</strong></span>
          <span>Usuário: <strong class="text-txt-primary">{{ originModalEntry.user_name ?? '—' }}</strong></span>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
