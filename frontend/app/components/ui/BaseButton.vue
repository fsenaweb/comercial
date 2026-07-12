<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit'
    loading?: boolean
    loadingText?: string
    disabled?: boolean
    variant?: 'primary' | 'ghost' | 'danger'
    block?: boolean
  }>(),
  { type: 'button', loading: false, loadingText: 'Carregando...', disabled: false, variant: 'primary', block: true },
)

const variantClasses: Record<string, string> = {
  primary: 'bg-brand text-brand-ink hover:brightness-95 disabled:hover:brightness-100',
  ghost: 'border border-border text-txt-secondary hover:border-border-strong hover:text-txt-primary',
  danger: 'border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold shadow-card transition hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-card"
    :class="[variantClasses[variant], block ? 'w-full' : '']"
  >
    <slot v-if="!loading" />
    <span v-else class="inline-flex items-center gap-2">
      <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent opacity-60" />
      {{ loadingText }}
    </span>
  </button>
</template>
