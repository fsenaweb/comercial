<script setup lang="ts">
interface BarDatum {
  label: string
  value: number
}

const props = withDefaults(
  defineProps<{
    data: BarDatum[]
    formatValue?: (value: number) => string
    tone?: 'brand' | 'emerald' | 'sky'
  }>(),
  { tone: 'brand' },
)

const toneClasses: Record<string, string> = {
  brand: 'bg-brand',
  emerald: 'bg-emerald-500',
  sky: 'bg-sky-500',
}

const maxValue = computed(() => Math.max(1, ...props.data.map((d) => d.value)))

function barHeight(value: number): string {
  return `${Math.max(4, Math.round((value / maxValue.value) * 100))}%`
}

function formatted(value: number): string {
  return props.formatValue ? props.formatValue(value) : String(value)
}
</script>

<template>
  <div v-if="data.length === 0" class="flex h-36 items-center justify-center rounded-xl bg-surface-subtle text-xs text-txt-muted">
    Sem dados no período
  </div>
  <div v-else class="flex h-36 items-end gap-2.5">
    <div v-for="item in data" :key="item.label" class="group flex min-w-0 flex-1 flex-col items-center gap-1.5">
      <div class="relative flex h-28 w-full items-end justify-center">
        <div
          class="w-full max-w-[26px] rounded-t-md transition-all"
          :class="toneClasses[tone]"
          :style="{ height: barHeight(item.value) }"
          :title="`${item.label}: ${formatted(item.value)}`"
        />
      </div>
      <span class="truncate text-[10px] font-medium text-txt-muted">{{ item.label }}</span>
    </div>
  </div>
</template>
