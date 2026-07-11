<script setup lang="ts">
defineProps<{
  modelValue: string | null
  label: string
  type?: string
  error?: string | null
  autocomplete?: string
}>()

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <label class="block">
    <span class="mb-1 block text-sm font-medium text-txt-secondary">{{ label }}</span>
    <input
      :type="type ?? 'text'"
      :value="modelValue"
      :autocomplete="autocomplete"
      class="w-full rounded-xl border border-border bg-surface-raised px-3 py-2 text-sm text-txt-primary transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
      :class="{ '!border-rose-500 focus:!ring-rose-500/30': error }"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    >
    <span v-if="error" class="mt-1 block text-sm text-rose-600">{{ error }}</span>
  </label>
</template>
