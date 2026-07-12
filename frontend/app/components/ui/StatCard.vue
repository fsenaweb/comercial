<script setup lang="ts">
import type { Component } from 'vue'

withDefaults(
  defineProps<{
    label: string
    value: string | number
    subtext?: string
    icon: Component
    tone?: 'emerald' | 'sky' | 'violet' | 'amber' | 'warning' | 'danger'
  }>(),
  { tone: 'emerald' },
)

const toneClasses: Record<string, string> = {
  emerald: 'bg-emerald-100 text-emerald-600',
  sky: 'bg-sky-100 text-sky-600',
  violet: 'bg-violet-100 text-violet-600',
  amber: 'bg-amber-100 text-amber-700',
  warning: 'bg-amber-100 text-amber-700',
  danger: 'bg-rose-100 text-rose-600',
}
</script>

<template>
  <div
    class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card"
    :class="{ '!border-l-4 !border-l-rose-400': tone === 'danger' }"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-semibold tracking-wide text-txt-muted uppercase">{{ label }}</p>
        <p class="mt-1 truncate font-display text-2xl font-bold text-txt-primary">{{ value }}</p>
        <p v-if="subtext" class="mt-1 text-xs text-txt-muted">{{ subtext }}</p>
      </div>
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="toneClasses[tone]">
        <component :is="icon" :size="20" />
      </span>
    </div>
  </div>
</template>
