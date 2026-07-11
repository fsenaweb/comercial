<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit'
    loading?: boolean
    disabled?: boolean
  }>(),
  { type: 'button', loading: false, disabled: false },
)
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex w-full items-center justify-center rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-brand-ink shadow-card transition hover:brightness-95 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:brightness-100"
  >
    <slot v-if="!loading" />
    <span v-else>Carregando...</span>
  </button>
</template>
