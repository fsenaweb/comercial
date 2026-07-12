<script setup lang="ts">
import type { Component } from 'vue'
import { Pencil, Plus, Trash2 } from 'lucide-vue-next'

interface CatalogField {
  key: string
  label: string
  type: 'text' | 'textarea' | 'select' | 'switch'
  options?: { value: string | number; label: string }[]
  secondary?: boolean
}

type CatalogItem = { id: number; [key: string]: unknown }

const props = defineProps<{
  resource: string
  title: string
  titlePlural: string
  description: string
  icon: Component
  tone: 'emerald' | 'sky' | 'violet' | 'amber' | 'teal'
  fields: CatalogField[]
  addLabel: string
}>()

const toneClasses: Record<string, string> = {
  emerald: 'bg-emerald-100 text-emerald-600',
  sky: 'bg-sky-100 text-sky-600',
  violet: 'bg-violet-100 text-violet-600',
  amber: 'bg-amber-100 text-amber-700',
  teal: 'bg-teal-100 text-teal-600',
}

const api = useResourceApi<CatalogItem>(props.resource)
const { parse, firstFieldError } = useApiError()
const auth = useAuthStore()

const items = ref<CatalogItem[]>([])
const loading = ref(true)
const createModalOpen = ref(false)
const listModalOpen = ref(false)
const modalSaving = ref(false)
const modalError = ref<unknown>(null)
const editingId = ref<number | null>(null)

function emptyForm() {
  return Object.fromEntries(props.fields.map((f) => [f.key, f.type === 'switch' ? true : ''])) as Record<string, string | boolean>
}

const form = reactive<Record<string, string | boolean>>(emptyForm())

async function load() {
  loading.value = true
  items.value = await api.list()
  loading.value = false
}

onMounted(load)

function openCreateModal() {
  editingId.value = null
  Object.assign(form, emptyForm())
  modalError.value = null
  createModalOpen.value = true
}

function openEditModal(item: CatalogItem) {
  listModalOpen.value = false
  editingId.value = item.id
  for (const field of props.fields) {
    form[field.key] = field.type === 'switch' ? Boolean(item[field.key]) : String(item[field.key] ?? '')
  }
  modalError.value = null
  createModalOpen.value = true
}

function closeCreateModal() {
  createModalOpen.value = false
}

function openListModal() {
  listModalOpen.value = true
}

function closeListModal() {
  listModalOpen.value = false
}

async function handleSubmit() {
  modalSaving.value = true
  modalError.value = null

  try {
    if (editingId.value) {
      await api.update(editingId.value, form)
    } else {
      await api.create(form)
    }
    closeCreateModal()
    await load()
  } catch (err) {
    modalError.value = err
  } finally {
    modalSaving.value = false
  }
}

async function handleDelete(item: CatalogItem) {
  if (!confirm(`Excluir ${props.title.toLowerCase()} "${resolveLabel(item)}"?`)) return

  await api.remove(item.id)
  await load()
}

const primaryField = computed(() => props.fields.find((f) => f.key === 'name') ?? props.fields[0]!)
const secondaryFields = computed(() => props.fields.filter((f) => f.secondary))

function resolveDisplay(item: CatalogItem, field: CatalogField): string {
  const raw = item[field.key]
  if (field.type === 'select') {
    return field.options?.find((o) => String(o.value) === String(raw))?.label ?? '—'
  }
  if (field.type === 'switch') {
    return raw ? 'Ativo' : 'Inativo'
  }
  return raw == null || raw === '' ? '—' : String(raw)
}

function resolveLabel(item: CatalogItem) {
  return resolveDisplay(item, primaryField.value)
}

function resolveSecondaryLine(item: CatalogItem) {
  return secondaryFields.value
    .map((f) => resolveDisplay(item, f))
    .filter((v) => v !== '—')
    .join(' · ')
}

const previewChips = computed(() => items.value.slice(0, 5).map(resolveLabel))
</script>

<template>
  <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
    <div class="flex items-start justify-between gap-3">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="toneClasses[tone]">
        <component :is="icon" :size="20" />
      </span>
      <StatusBadge :label="`${items.length} ${items.length === 1 ? 'item' : 'itens'}`" tone="neutral" />
    </div>

    <p class="mt-3 font-display text-base font-bold text-txt-primary">{{ titlePlural }}</p>
    <p class="mt-1 text-sm text-txt-secondary">{{ description }}</p>

    <div class="mt-3 flex flex-wrap gap-1.5">
      <StatusBadge v-for="(chip, index) in previewChips" :key="index" :label="chip" tone="neutral" />
      <span v-if="!loading && items.length === 0" class="text-xs text-txt-muted">Nenhum item cadastrado ainda.</span>
    </div>

    <div class="mt-4 flex gap-3">
      <BaseButton v-if="auth.isAdmin" :block="false" @click="openCreateModal">
        <Plus :size="16" />
        {{ addLabel }}
      </BaseButton>
      <BaseButton variant="ghost" :block="false" @click="openListModal">Ver todos</BaseButton>
    </div>

    <BaseModal
      :open="createModalOpen"
      :title="title"
      subtitle="Cadastre um novo item para usar no formulário de produto."
      size="md"
      @close="closeCreateModal"
    >
      <form class="space-y-4" @submit.prevent="handleSubmit">
        <template v-for="field in fields" :key="field.key">
          <BaseInput
            v-if="field.type === 'text'"
            :model-value="(form[field.key] as string) ?? ''"
            :label="field.label"
            :error="firstFieldError(modalError, field.key)"
            @update:model-value="form[field.key] = $event"
          />
          <BaseTextarea
            v-else-if="field.type === 'textarea'"
            :model-value="(form[field.key] as string) ?? ''"
            :label="field.label"
            :error="firstFieldError(modalError, field.key)"
            @update:model-value="form[field.key] = $event"
          />
          <BaseSelect
            v-else-if="field.type === 'select'"
            :model-value="(form[field.key] as string) ?? ''"
            :label="field.label"
            :options="field.options ?? []"
            :error="firstFieldError(modalError, field.key)"
            @update:model-value="form[field.key] = $event"
          />
          <BaseSwitch
            v-else-if="field.type === 'switch'"
            :model-value="Boolean(form[field.key])"
            :label="field.label"
            @update:model-value="form[field.key] = $event"
          />
        </template>

        <p v-if="modalError" class="text-sm text-rose-600">{{ parse(modalError).message }}</p>

        <div class="flex justify-end gap-3 border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="closeCreateModal">Cancelar</BaseButton>
          <BaseButton type="submit" :loading="modalSaving" :block="false">Salvar</BaseButton>
        </div>
      </form>
    </BaseModal>

    <BaseModal :open="listModalOpen" :title="`${titlePlural} (${items.length})`" size="md" @close="closeListModal">
      <ul class="divide-y divide-border">
        <li v-if="loading" class="py-6 text-center text-sm text-txt-muted">Carregando...</li>
        <li v-else-if="items.length === 0" class="py-6 text-center text-sm text-txt-muted">Nenhum item cadastrado.</li>
        <li v-for="item in items" v-else :key="item.id" class="flex items-center justify-between gap-3 py-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-txt-primary">{{ resolveLabel(item) }}</p>
            <p v-if="resolveSecondaryLine(item)" class="truncate text-xs text-txt-muted">{{ resolveSecondaryLine(item) }}</p>
          </div>
          <div v-if="auth.isAdmin" class="flex shrink-0 gap-1">
            <IconButton :icon="Pencil" label="Editar" @click="openEditModal(item)" />
            <IconButton :icon="Trash2" label="Excluir" tone="danger" @click="handleDelete(item)" />
          </div>
        </li>
      </ul>
    </BaseModal>
  </div>
</template>
