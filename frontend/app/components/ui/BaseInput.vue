<script setup lang="ts">
import type { Component } from 'vue'

const props = defineProps<{
  modelValue: string | number | null
  label: string
  type?: string
  error?: string | null
  autocomplete?: string
  icon?: Component
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

// Campos com máscara (moeda, CEP, CPF/CNPJ) recalculam o valor a cada tecla e
// às vezes o resultado não muda de uma tecla pra outra (ex.: já bateu o limite
// de dígitos) - nesse caso o Vue não teria motivo pra re-patchar o DOM (o prop
// "modelValue" ficou igual), e o navegador deixa o caractere digitado "grudado"
// na tela mesmo o estado real (form.x) já estando correto. Forçamos a
// sincronização do elemento nativo após o próximo tick pra nunca deixar a tela
// mostrar algo diferente do valor que o app realmente tem.
async function handleInput(event: Event) {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
  await nextTick()
  const expected = props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue)
  if (target.value !== expected) {
    target.value = expected
  }
}
</script>

<template>
  <label class="block">
    <span class="mb-1 block text-sm font-medium text-txt-secondary">{{ label }}</span>
    <span class="relative block">
      <span v-if="icon" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-txt-muted">
        <component :is="icon" :size="16" />
      </span>
      <input
        :type="type ?? 'text'"
        :value="modelValue"
        :autocomplete="autocomplete"
        :disabled="disabled"
        class="w-full rounded-xl border border-border bg-surface-raised px-3 py-2 text-sm text-txt-primary transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30 disabled:cursor-not-allowed disabled:bg-surface-subtle disabled:text-txt-muted"
        :class="[{ '!border-rose-500 focus:!ring-rose-500/30': error }, icon ? 'pl-9' : '']"
        @input="handleInput"
      >
    </span>
    <span v-if="error" class="mt-1 block text-sm text-rose-600">{{ error }}</span>
  </label>
</template>
