<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next'

defineProps<{
  modelValue: string | number | null
  label: string
  options: { value: string | number; label: string }[]
  error?: string | null
  placeholder?: string
  disabled?: boolean
}>()

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <label class="block">
    <span class="mb-1 block text-sm font-medium text-txt-secondary">{{ label }}</span>
    <span class="relative block">
      <select
        :value="modelValue ?? ''"
        :disabled="disabled"
        class="w-full appearance-none rounded-xl border border-border bg-surface-raised px-3 py-2 pr-9 text-sm text-txt-primary transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30 disabled:cursor-not-allowed disabled:bg-surface-subtle disabled:text-txt-muted"
        :class="{ '!border-rose-500 focus:!ring-rose-500/30': error }"
        @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
      >
        <option value="" disabled>{{ placeholder ?? 'Selecione...' }}</option>
        <option v-for="option in options" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
      <ChevronDown :size="16" class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-txt-muted" />
    </span>
    <span v-if="error" class="mt-1 block text-sm text-rose-600">{{ error }}</span>
  </label>
</template>
