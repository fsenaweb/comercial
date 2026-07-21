<script setup lang="ts">
import { Plus, Search, Settings, Tag, X } from 'lucide-vue-next'
import type { ProductVariationRow } from '~/composables/useProductVariationSearch'

interface ContentFields {
  name: boolean
  price: boolean
  code: boolean
  barcode: boolean
  store_name: boolean
}

interface FontSizes {
  name: number
  price: number
  barcode: number
}

interface LabelSettings {
  page_width: number
  page_height: number
  margin_top: number
  margin_bottom: number
  margin_left: number
  margin_right: number
  columns: number
  rows: number
  label_width: number
  label_height: number
  content_fields: ContentFields
  font_sizes: FontSizes
}

interface Preset {
  key: string
  title: string
  desc: string
  w: number
  h: number
  cols: number
}

interface SelectedLabel {
  row: ProductVariationRow
  quantity: number
}

function defaultLabelSettings(): LabelSettings {
  return {
    page_width: 210,
    page_height: 297,
    margin_top: 4.23,
    margin_bottom: 4.3,
    margin_left: 6.01,
    margin_right: 6.05,
    columns: 3,
    rows: 9,
    label_width: 63.5,
    label_height: 31,
    content_fields: { name: true, price: true, code: true, barcode: true, store_name: false },
    font_sizes: { name: 9, price: 12, barcode: 8 },
  }
}

const presets: Preset[] = [
  { key: 'termica50x30', title: 'Térmica 50x30mm', desc: 'Etiqueta adesiva 50x30mm em bobina térmica (uma por página)', w: 50, h: 30, cols: 1 },
  { key: 'termica40x30', title: 'Térmica 40x30mm', desc: 'Etiqueta adesiva 40x30mm (mais comum em lojas)', w: 40, h: 30, cols: 1 },
  { key: 'termica80', title: 'Térmica 80mm - 2 colunas (40x30mm)', desc: 'Bobina térmica de 80mm com 2 etiquetas de 40x30mm lado a lado', w: 40, h: 30, cols: 2 },
  { key: 'termica30x20', title: 'Térmica 30x20mm', desc: 'Etiqueta pequena 30x20mm (só código de barras e preço)', w: 30, h: 20, cols: 1 },
  { key: 'a43col', title: 'A4 - 3 colunas', desc: 'Folha A4 tradicional com 3 colunas de etiquetas', w: 63.5, h: 31, cols: 3 },
]

const contentFieldDefs: { key: keyof ContentFields, label: string }[] = [
  { key: 'name', label: 'Nome do produto' },
  { key: 'price', label: 'Preço' },
  { key: 'code', label: 'Código do produto' },
  { key: 'barcode', label: 'Código de barras' },
  { key: 'store_name', label: 'Nome da loja' },
]

const fontFieldDefs: { key: keyof FontSizes, label: string }[] = [
  { key: 'name', label: 'Nome do produto' },
  { key: 'price', label: 'Preço' },
  { key: 'barcode', label: 'Código de barras' },
]

const tabs: { key: 'pagina' | 'grade' | 'etiqueta' | 'conteudo' | 'fontes', label: string }[] = [
  { key: 'pagina', label: 'Página' },
  { key: 'grade', label: 'Grade' },
  { key: 'etiqueta', label: 'Etiqueta' },
  { key: 'conteudo', label: 'Conteúdo' },
  { key: 'fontes', label: 'Fontes' },
]

const { search: searchProductVariations } = useProductVariationSearch()
const { parse } = useApiError()

const loading = ref(true)
const storeName = ref('')
const settings = ref<LabelSettings>(defaultLabelSettings())
const selected = ref<SelectedLabel[]>([])

async function loadAll() {
  loading.value = true
  const api = useApi()
  const storeSettingsRes = await api<{ data: { name: string, trade_name: string | null, label_settings: LabelSettings | null } }>('/store-settings')
  storeName.value = storeSettingsRes.data.trade_name || storeSettingsRes.data.name
  if (storeSettingsRes.data.label_settings) {
    settings.value = storeSettingsRes.data.label_settings
  }
  loading.value = false
}

await loadAll()

// ---- Modal de busca de produtos ----
const searchOpen = ref(false)
const searchQuery = ref('')
const searchResults = ref<ProductVariationRow[]>([])
let searchDebounce: ReturnType<typeof setTimeout> | null = null

watch(searchQuery, (query) => {
  if (searchDebounce) clearTimeout(searchDebounce)
  if (!query.trim()) {
    searchResults.value = []
    return
  }
  searchDebounce = setTimeout(async () => {
    searchResults.value = await searchProductVariations(query, 20)
  }, 200)
})

function openSearch() {
  searchQuery.value = ''
  searchResults.value = []
  searchOpen.value = true
}

function addProduct(row: ProductVariationRow) {
  const existing = selected.value.find((s) => s.row.variation.id === row.variation.id)
  if (existing) {
    existing.quantity += 1
  } else {
    selected.value.push({ row, quantity: 1 })
  }
  searchOpen.value = false
}

function removeProduct(variationId: number) {
  selected.value = selected.value.filter((s) => s.row.variation.id !== variationId)
}

const totalLabels = computed(() => selected.value.reduce((sum, s) => sum + (s.quantity || 0), 0))

// ---- Modal de configurações ----
const configOpen = ref(false)
const activeTab = ref<typeof tabs[number]['key']>('pagina')
const configError = ref<unknown>(null)
const savingConfig = ref(false)

function openConfig() {
  activeTab.value = 'pagina'
  configError.value = null
  configOpen.value = true
}

function applyPreset(preset: Preset) {
  settings.value.label_width = preset.w
  settings.value.label_height = preset.h
  settings.value.columns = preset.cols
}

function restoreDefaults() {
  settings.value = defaultLabelSettings()
}

async function saveConfig() {
  savingConfig.value = true
  configError.value = null
  try {
    const api = useApi()
    await api('/store-settings/label-settings', { method: 'PUT', body: settings.value })
    configOpen.value = false
  } catch (err) {
    configError.value = err
  } finally {
    savingConfig.value = false
  }
}

const fitCount = computed(() => settings.value.columns * settings.value.rows)
const previewLabels = computed(() => Array.from({ length: Math.min(fitCount.value, 30) }, (_, i) => i))

// ---- Impressão ----
const printing = ref(false)
const printError = ref<unknown>(null)

async function handlePrint() {
  if (selected.value.length === 0) return

  // window.open síncrono (antes do await) pra não ser bloqueado pelo popup
  // blocker do navegador, que só permite abrir janelas a partir de um gesto
  // do usuário sem trabalho assíncrono no meio.
  const printWindow = window.open('', '_blank')
  printing.value = true
  printError.value = null

  try {
    const api = useApi()
    const html = await api<string>('/labels/print', {
      baseURL: '',
      method: 'POST',
      responseType: 'text',
      body: {
        products: selected.value.map((s) => ({ variation_id: s.row.variation.id, quantity: s.quantity })),
        ...settings.value,
      },
    })
    if (printWindow) {
      printWindow.document.write(html)
      printWindow.document.close()
    }
  } catch (err) {
    printWindow?.close()
    printError.value = err
  } finally {
    printing.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center gap-3.5">
      <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-100 text-sky-600">
        <Tag :size="20" />
      </span>
      <div>
        <h1 class="font-display text-2xl font-extrabold text-txt-primary">Impressão de etiquetas</h1>
        <p class="text-sm text-txt-secondary">Busque e selecione produtos da lista para realizar a impressão de etiquetas.</p>
      </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised p-5.5 shadow-card">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="font-display text-sm font-bold text-txt-primary">Gerador de Etiquetas</p>
          <p class="text-sm text-txt-secondary">Configure o layout, escolha os campos e ajuste o tamanho da fonte de cada informação.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
          <BaseButton :block="false" @click="openSearch">
            <Plus :size="15" />
            Adicionar produtos
          </BaseButton>
          <BaseButton variant="ghost" :block="false" @click="openConfig">
            <Settings :size="15" />
            Configurações das etiquetas
          </BaseButton>
          <BaseButton :block="false" :disabled="selected.length === 0" :loading="printing" loading-text="Gerando..." @click="handlePrint">
            Imprimir
          </BaseButton>
        </div>
      </div>
      <p v-if="printError" class="mt-3 text-sm text-rose-600">{{ parse(printError).message }}</p>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised p-5.5 shadow-card">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="font-display text-sm font-bold text-txt-primary">Resumo da configuração</p>
          <p class="text-sm text-txt-secondary">{{ settings.columns }} coluna(s) · {{ settings.label_width }}mm x {{ settings.label_height }}mm</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
          <StatusBadge :label="`Código: código de barras`" />
          <StatusBadge :label="`Preview: ${totalLabels || fitCount} etiqueta(s)`" />
        </div>
      </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised p-5.5 shadow-card">
      <p class="font-display text-sm font-bold text-txt-primary">Produtos selecionados</p>
      <p class="mb-4 text-sm text-txt-secondary">{{ selected.length }} produto(s) selecionado(s).</p>

      <div v-if="selected.length === 0" class="rounded-xl border border-dashed border-border py-11 text-center text-sm text-txt-muted">
        Nenhum produto selecionado para impressão.
      </div>

      <div v-else class="space-y-2.5">
        <div
          v-for="item in selected"
          :key="item.row.variation.id"
          class="flex flex-wrap items-center justify-between gap-3.5 rounded-xl border border-border px-4 py-3"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-txt-primary">{{ item.row.productName }}</p>
            <p class="text-[11px] text-txt-muted">
              {{ item.row.variationLabel ? `${item.row.variationLabel} · ` : '' }}Cód. {{ item.row.variation.product_code }}
            </p>
          </div>
          <div class="flex items-center gap-3.5">
            <label class="flex items-center gap-2">
              <span class="text-xs font-semibold text-txt-secondary whitespace-nowrap">Qtde. etiquetas</span>
              <input v-model.number="item.quantity" type="number" min="1" class="w-16 rounded-lg border border-border px-2 py-1.5 text-center text-sm">
            </label>
            <IconButton :icon="X" label="Remover" tone="danger" @click="removeProduct(item.row.variation.id)" />
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: BUSCAR PRODUTOS -->
    <BaseModal :open="searchOpen" title="Buscar produtos" subtitle="Pesquise pelo nome ou código e informe a quantidade de etiquetas." @close="searchOpen = false">
      <label class="relative mb-3 block">
        <Search :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-txt-muted" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Nome ou código do produto"
          autofocus
          class="w-full rounded-xl border border-border py-2.5 pr-3 pl-9 text-sm"
        >
      </label>

      <div v-if="!searchQuery.trim()" class="rounded-xl bg-surface-subtle py-8 px-5 text-center text-sm text-txt-muted">
        Digite o nome, código interno ou código de barras do produto para buscar.
      </div>

      <div v-else class="max-h-80 space-y-1.5 overflow-y-auto">
        <div
          v-for="row in searchResults"
          :key="row.key"
          class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-border px-3.5 py-2.5 hover:bg-surface-subtle"
          @click="addProduct(row)"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-txt-primary">{{ row.productName }}</p>
            <p class="text-[11px] text-txt-muted">Cód. {{ row.variation.product_code }}</p>
          </div>
          <Plus :size="15" class="shrink-0 text-emerald-600" />
        </div>
        <p v-if="searchResults.length === 0" class="py-6 text-center text-sm text-txt-muted">Nenhum produto encontrado.</p>
      </div>
    </BaseModal>

    <!-- MODAL: CONFIGURAÇÕES DAS ETIQUETAS -->
    <BaseModal :open="configOpen" size="xl" title="Configurações das Etiquetas" subtitle="Ajuste página, grade, conteúdo, fontes e acompanhe o preview em tempo real." @close="configOpen = false">
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="cursor-pointer rounded-full px-4 py-2 text-sm font-bold"
          :class="activeTab === tab.key ? 'bg-emerald-600 text-white' : 'bg-surface-subtle text-txt-secondary'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-[1.5fr_1fr]">
        <!-- LEFT: tab content -->
        <div class="space-y-4.5 border-b border-border pb-4 md:border-r md:border-b-0 md:pr-5.5 md:pb-0">
          <div v-if="activeTab === 'pagina'" class="space-y-4.5">
            <div>
              <span class="mb-2.5 block text-sm font-bold text-txt-primary">Modelos rápidos</span>
              <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <div
                  v-for="preset in presets"
                  :key="preset.key"
                  class="cursor-pointer rounded-xl border border-border p-3.5"
                  :class="settings.label_width === preset.w && settings.label_height === preset.h && settings.columns === preset.cols ? 'bg-emerald-50 border-emerald-300' : ''"
                  @click="applyPreset(preset)"
                >
                  <p class="mb-0.5 text-sm font-bold text-txt-primary">{{ preset.title }}</p>
                  <p class="text-[11.5px] text-txt-muted">{{ preset.desc }}</p>
                </div>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <BaseInput v-model.number="settings.page_width" type="number" label="Largura da página (mm)" />
              <BaseInput v-model.number="settings.page_height" type="number" label="Altura da página (mm)" />
            </div>
            <p class="text-[11.5px] text-txt-muted">Use 58mm ou 80mm para bobina térmica. Em impressoras térmicas, deixe margens pequenas e grade com uma coluna.</p>
            <div>
              <span class="mb-2.5 block text-sm font-bold text-txt-primary">Margens da página</span>
              <div class="grid grid-cols-2 gap-4">
                <BaseInput v-model.number="settings.margin_top" type="number" label="Superior (mm)" />
                <BaseInput v-model.number="settings.margin_bottom" type="number" label="Inferior (mm)" />
                <BaseInput v-model.number="settings.margin_left" type="number" label="Esquerda (mm)" />
                <BaseInput v-model.number="settings.margin_right" type="number" label="Direita (mm)" />
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'grade'" class="grid grid-cols-2 gap-4">
            <BaseInput v-model.number="settings.columns" type="number" label="Colunas" />
            <BaseInput v-model.number="settings.rows" type="number" label="Linhas" />
          </div>

          <div v-else-if="activeTab === 'etiqueta'" class="grid grid-cols-2 gap-4">
            <BaseInput v-model.number="settings.label_width" type="number" label="Largura da etiqueta (mm)" />
            <BaseInput v-model.number="settings.label_height" type="number" label="Altura da etiqueta (mm)" />
          </div>

          <div v-else-if="activeTab === 'conteudo'" class="space-y-2.5">
            <span class="mb-1 block text-sm font-bold text-txt-primary">Campos exibidos na etiqueta</span>
            <div
              v-for="field in contentFieldDefs"
              :key="field.key"
              class="flex cursor-pointer items-center justify-between rounded-xl border border-border px-3.5 py-3"
              @click="settings.content_fields[field.key] = !settings.content_fields[field.key]"
            >
              <span class="text-sm font-semibold text-txt-primary">{{ field.label }}</span>
              <BaseSwitch :model-value="settings.content_fields[field.key]" @update:model-value="settings.content_fields[field.key] = $event" />
            </div>
          </div>

          <div v-else-if="activeTab === 'fontes'" class="space-y-4">
            <span class="mb-1 block text-sm font-bold text-txt-primary">Tamanho da fonte por campo</span>
            <div v-for="field in fontFieldDefs" :key="field.key" class="flex items-center justify-between gap-3.5">
              <span class="flex-1 text-sm font-semibold text-txt-secondary">{{ field.label }}</span>
              <input v-model.number="settings.font_sizes[field.key]" type="range" min="6" max="24" class="flex-[2] accent-emerald-600">
              <span class="w-10 text-right text-xs text-txt-muted">{{ settings.font_sizes[field.key] }}px</span>
            </div>
          </div>
        </div>

        <!-- RIGHT: preview -->
        <div class="space-y-3.5">
          <div>
            <span class="text-sm font-bold text-txt-primary">Preview</span>
            <p class="mt-0.5 text-xs text-txt-muted">Visualização aproximada da página com as configurações atuais.</p>
          </div>
          <div class="grid grid-cols-2 gap-2.5 text-xs">
            <div class="rounded-lg bg-surface-subtle px-3 py-2.5">
              <p class="font-bold tracking-wide text-txt-muted uppercase">Página</p>
              <p class="mt-0.5 font-bold text-txt-primary">{{ settings.page_width }} × {{ settings.page_height }} mm</p>
            </div>
            <div class="rounded-lg bg-surface-subtle px-3 py-2.5">
              <p class="font-bold tracking-wide text-txt-muted uppercase">Etiqueta</p>
              <p class="mt-0.5 font-bold text-txt-primary">{{ settings.label_width }} × {{ settings.label_height }} mm</p>
            </div>
            <div class="col-span-2 rounded-lg bg-surface-subtle px-3 py-2.5">
              <p class="font-bold tracking-wide text-txt-muted uppercase">Cabem na página</p>
              <p class="mt-0.5 font-bold text-txt-primary">{{ settings.columns }} col · {{ settings.rows }} lin ({{ fitCount }})</p>
            </div>
          </div>
          <div class="flex-1 rounded-xl border border-border bg-surface-subtle p-3.5">
            <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${settings.columns}, 1fr)` }">
              <div v-for="i in previewLabels" :key="i" class="flex flex-col items-center gap-1 rounded border border-border bg-surface-raised p-1.5">
                <span v-if="settings.content_fields.name" class="text-center text-[8px] font-semibold text-txt-secondary">{{ storeName || 'Nome do produto' }}</span>
                <div v-if="settings.content_fields.barcode" class="h-4 w-full" style="background:repeating-linear-gradient(90deg, #222 0 2px, #fff 2px 3px);" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <p v-if="configError" class="mt-4 text-sm text-rose-600">{{ parse(configError).message }}</p>

      <div class="mt-5.5 flex items-center justify-between border-t border-border pt-4.5">
        <BaseButton type="button" variant="ghost" :block="false" @click="restoreDefaults">Restaurar padrão</BaseButton>
        <div class="flex gap-2.5">
          <BaseButton type="button" variant="ghost" :block="false" @click="configOpen = false">Cancelar</BaseButton>
          <BaseButton type="button" :loading="savingConfig" :block="false" @click="saveConfig">Salvar configurações</BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
