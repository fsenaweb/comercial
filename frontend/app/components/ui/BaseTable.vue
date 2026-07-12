<script setup lang="ts" generic="T extends { id: number }">
defineProps<{
  items: T[]
  columns: { key: string; label: string }[]
  loading?: boolean
  emptyMessage?: string
}>()
</script>

<template>
  <div>
    <p v-if="!loading" class="mb-2 text-xs text-txt-muted">Exibindo {{ items.length }} registro(s)</p>
    <div class="overflow-x-auto rounded-2xl border border-border bg-surface-raised shadow-card">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-border text-xs font-semibold tracking-wide text-txt-muted uppercase">
            <th v-for="column in columns" :key="column.key" class="px-4 py-3">{{ column.label }}</th>
            <th class="px-4 py-3 text-right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columns.length + 1" class="px-4 py-6 text-center text-txt-muted">
              Carregando...
            </td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td :colspan="columns.length + 1" class="px-4 py-6 text-center text-txt-muted">
              {{ emptyMessage ?? 'Nenhum registro encontrado.' }}
            </td>
          </tr>
          <tr
            v-for="item in items"
            v-else
            :key="item.id"
            class="border-b border-border last:border-0 hover:bg-surface-subtle"
          >
            <td v-for="column in columns" :key="column.key" class="px-4 py-3 text-txt-primary">
              <slot :name="`cell-${column.key}`" :item="item">
                {{ (item as Record<string, unknown>)[column.key] }}
              </slot>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-1">
                <slot name="actions" :item="item" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
