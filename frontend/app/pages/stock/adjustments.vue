<script setup lang="ts">
import { AlertTriangle, ClipboardEdit, Package, Search } from 'lucide-vue-next'
import type { ProductVariationRow } from '~/composables/useProductVariationSearch'

type SkuOption = ProductVariationRow

const api = useApi()
const { search: searchProductVariations } = useProductVariationSearch()
const { parse, firstFieldError } = useApiError()

const selected = ref<SkuOption | null>(null)
const newQuantity = ref<number | null>(null)
const reason = ref('')
const saving = ref(false)
const error = ref<unknown>(null)
const successMessage = ref<string | null>(null)

function selectSku(row: SkuOption) {
  selected.value = row
  newQuantity.value = row.variation.current_quantity
  error.value = null
  successMessage.value = null
}

// ---- Modal de busca de produto (F2), mesmo padrão do PDV - busca no banco
// (debounced). Antes filtrava uma lista fixa carregada no mount (só a
// primeira página de `GET /products`, que virou paginado - achado do
// cliente, 2026-07-21: a busca não encontrava produto fora dela).
const showPicker = ref(false)
const pickerSearch = ref('')
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

function openPicker() {
  pickerSearch.value = ''
  filteredPickerRows.value = []
  showPicker.value = true
}

function choosePickerRow(row: SkuOption) {
  selectSku(row)
  showPicker.value = false
}

function handleGlobalKeydown(event: KeyboardEvent) {
  if (event.key !== 'F2' || selected.value) return
  event.preventDefault()
  openPicker()
}

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown))

function clearSelection() {
  selected.value = null
  newQuantity.value = null
  reason.value = ''
}

const delta = computed(() => {
  if (!selected.value || newQuantity.value === null) return 0
  return newQuantity.value - selected.value.variation.current_quantity
})

const excessWarning = computed(() => {
  if (!selected.value || newQuantity.value === null) return null
  const max = selected.value.variation.max_quantity
  if (max === null || newQuantity.value <= max) return null
  return `Estoque máximo cadastrado é ${max} - este ajuste deixará o saldo acima do limite.`
})

async function handleSubmit() {
  if (!selected.value || newQuantity.value === null) return
  saving.value = true
  error.value = null
  successMessage.value = null

  try {
    await api('/stock-movements/adjustment', {
      method: 'POST',
      body: {
        product_variation_id: selected.value.variation.id,
        new_quantity: newQuantity.value,
        reason: reason.value,
      },
    })
    successMessage.value = `Estoque de "${selected.value.productName}" ajustado para ${newQuantity.value} unidade(s).`
    clearSelection()
  } catch (err) {
    error.value = err
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Ajuste de Estoque</h1>
      <p class="text-sm text-txt-secondary">Corrija a quantidade em estoque após uma contagem, avaria ou outra divergência - sempre gerando um registro no Kardex.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1.3fr_1fr]">
      <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <div class="mb-4 flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
            <ClipboardEdit :size="18" />
          </span>
          <div>
            <p class="font-display text-sm font-bold text-txt-primary">Novo ajuste</p>
            <p class="text-xs text-txt-secondary">Busque o produto, informe a quantidade contada e o motivo.</p>
          </div>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div v-if="!selected">
            <label class="mb-1 block text-sm font-medium text-txt-secondary">Produto</label>
            <button
              type="button"
              class="flex w-full cursor-pointer items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-left text-txt-muted hover:border-brand"
              @click="openPicker"
            >
              <Search :size="15" />
              <span class="flex-1 text-sm">Clique ou tecle <strong class="text-txt-secondary">F2</strong> para buscar produto...</span>
            </button>
          </div>

          <div v-else class="rounded-xl border border-border p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-bold text-txt-primary">{{ selected.productName }}</p>
                <p class="text-xs text-txt-muted">Cód. {{ selected.variation.product_code }}</p>
              </div>
              <BaseButton type="button" variant="ghost" :block="false" @click="clearSelection">Trocar</BaseButton>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4">
              <BaseInput :model-value="selected.variation.current_quantity" label="Quantidade atual" disabled />
              <BaseInput
                :model-value="newQuantity"
                type="number"
                label="Quantidade contada"
                :error="firstFieldError(error, 'new_quantity')"
                @update:model-value="newQuantity = $event === '' ? null : Math.max(0, Number($event))"
              />
            </div>
            <p class="mt-2 text-xs" :class="delta === 0 ? 'text-txt-muted' : delta > 0 ? 'text-emerald-700' : 'text-rose-600'">
              Diferença: {{ delta > 0 ? '+' : '' }}{{ delta }} unidade(s)
            </p>
            <p v-if="excessWarning" class="mt-1 flex items-center gap-1.5 text-xs text-sky-700">
              <AlertTriangle :size="13" class="shrink-0" />
              {{ excessWarning }}
            </p>

            <div class="mt-4">
              <BaseInput v-model="reason" label="Motivo do ajuste" placeholder="Ex.: contagem de inventário, avaria, perda..." :error="firstFieldError(error, 'reason')" />
            </div>
          </div>

          <p v-if="error && !firstFieldError(error, 'new_quantity') && !firstFieldError(error, 'reason')" class="text-sm text-rose-600">{{ parse(error).message }}</p>
          <p v-if="successMessage" class="text-sm text-emerald-700">{{ successMessage }}</p>

          <BaseButton type="submit" :disabled="!selected" :loading="saving" :block="false">Salvar ajuste</BaseButton>
        </form>
      </div>

      <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <div class="mb-3 flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-600">
            <Package :size="18" />
          </span>
          <p class="font-display text-sm font-bold text-txt-primary">Como funciona</p>
        </div>
        <ul class="space-y-2 text-xs text-txt-secondary">
          <li>• Informe a quantidade <strong>contada</strong> no estoque, não a diferença - o sistema calcula a diferença sozinho.</li>
          <li>• Todo ajuste gera um registro no <NuxtLink to="/stock/kardex" class="font-semibold text-brand underline">Kardex</NuxtLink>, com motivo, usuário e data.</li>
          <li>• O sistema não permite estoque negativo - um ajuste ou venda que resultaria em saldo negativo é bloqueado.</li>
          <li>• Para receber mercadoria de um fornecedor, use a tela <NuxtLink to="/stock/entries" class="font-semibold text-brand underline">Entradas de Estoque</NuxtLink> em vez desta.</li>
        </ul>
      </div>
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
          class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2.5 hover:bg-surface-subtle"
          @click="choosePickerRow(row)"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-bold text-txt-primary">{{ row.productName }}</p>
            <p class="text-[11.5px] text-txt-muted">Cód. {{ row.variation.product_code }} · {{ row.variation.current_quantity }} em estoque</p>
          </div>
          <BaseButton :block="false" @click.stop="choosePickerRow(row)">Escolher</BaseButton>
        </div>
        <p v-if="filteredPickerRows.length === 0" class="py-6 text-center text-sm text-txt-muted">Nenhum produto encontrado.</p>
      </div>
    </BaseModal>
  </div>
</template>
