<script setup lang="ts">
import type { Component } from 'vue'

withDefaults(
  defineProps<{
    icon: Component
    label: string
    tone?: 'default' | 'danger'
  }>(),
  { tone: 'default' },
)

defineEmits<{ click: [] }>()

const toneClasses: Record<string, string> = {
  default: 'text-txt-secondary hover:bg-surface-subtle hover:text-sky-600',
  danger: 'text-txt-secondary hover:bg-rose-50 hover:text-rose-600',
}
</script>

<template>
  <button
    type="button"
    :title="label"
    :aria-label="label"
    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg transition disabled:cursor-not-allowed disabled:opacity-50"
    :class="toneClasses[tone]"
    @click="$emit('click')"
  >
    <component :is="icon" :size="16" />
  </button>
</template>
