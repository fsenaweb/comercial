<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string
    tone?: 'success' | 'danger' | 'info' | 'warning' | 'neutral'
  }>(),
  { tone: 'neutral' },
)

const toneClasses: Record<string, string> = {
  success: 'bg-emerald-100 text-emerald-700',
  danger: 'bg-rose-100 text-rose-700',
  info: 'bg-sky-100 text-sky-700',
  warning: 'bg-amber-100 text-amber-700',
  neutral: 'bg-surface-subtle text-txt-secondary',
}
</script>

<template>
  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="toneClasses[tone]">
    {{ label }}
  </span>
</template>
