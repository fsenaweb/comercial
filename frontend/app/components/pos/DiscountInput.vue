<script setup lang="ts">
import type { DiscountType } from '~/utils/cartMath'

const props = defineProps<{
  type: DiscountType
  value: number
}>()

const emit = defineEmits<{
  'update:type': [type: DiscountType]
  'update:value': [value: number]
}>()

const { maskInput: maskCurrency, toNumber: currencyToNumber, format: formatCurrency } = useCurrencyMask()
const { maskInput: maskPercentage, toNumber: percentageToNumber, format: formatPercentage } = usePercentageMask()

const masked = computed(() =>
  props.type === 'percentage' ? formatPercentage(props.value) : formatCurrency(Math.round(props.value * 100)),
)

function handleInput(raw: string) {
  if (props.type === 'percentage') {
    emit('update:value', percentageToNumber(maskPercentage(raw)))
  } else {
    emit('update:value', currencyToNumber(maskCurrency(raw)))
  }
}

function setType(type: DiscountType) {
  emit('update:type', type)
  emit('update:value', 0)
}
</script>

<template>
  <div class="flex items-center overflow-hidden rounded-xl border border-border">
    <input
      :value="masked"
      type="text"
      inputmode="numeric"
      class="w-full min-w-0 flex-1 bg-transparent px-3 py-2.5 text-[16px] text-txt-primary focus:outline-none"
      @input="handleInput(($event.target as HTMLInputElement).value)"
    >
    <div class="flex flex-none border-l border-border">
      <button
        type="button"
        class="flex h-full w-9 cursor-pointer items-center justify-center text-[13px] font-bold transition"
        :class="type === 'fixed' ? 'bg-brand text-brand-ink' : 'text-txt-muted hover:bg-surface-subtle'"
        @click="setType('fixed')"
      >
        R$
      </button>
      <button
        type="button"
        class="flex h-full w-9 cursor-pointer items-center justify-center border-l border-border text-[13px] font-bold transition"
        :class="type === 'percentage' ? 'bg-brand text-brand-ink' : 'text-txt-muted hover:bg-surface-subtle'"
        @click="setType('percentage')"
      >
        %
      </button>
    </div>
  </div>
</template>
