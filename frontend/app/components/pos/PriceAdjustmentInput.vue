<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next'
import type { DiscountType } from '~/utils/cartMath'

// Envolve o DiscountInput (que só lida com magnitude positiva) e adiciona um
// botão de sinal - desconto (padrão) ou acréscimo, sem teto nem senha de
// admin (decisão do cliente, 2026-08-03: aumentar o preço na hora da venda,
// sem mexer no cadastro do produto). O valor emitido já sai com o sinal
// aplicado - o resto do sistema (backend e cartMath.ts) já soma quando o
// "desconto" é negativo, então nenhum outro lugar precisa saber que isso é
// um acréscimo.
const props = defineProps<{
  type: DiscountType
  value: number
}>()

const emit = defineEmits<{
  'update:type': [type: DiscountType]
  'update:value': [value: number]
}>()

const isMarkup = computed(() => props.value < 0)
const magnitude = computed(() => Math.abs(props.value))

function toggleSign() {
  emit('update:value', isMarkup.value ? magnitude.value : -magnitude.value)
}

function handleMagnitudeChange(newMagnitude: number) {
  emit('update:value', isMarkup.value ? -newMagnitude : newMagnitude)
}
</script>

<template>
  <div class="flex items-center gap-1.5">
    <button
      type="button"
      class="flex h-9 w-9 flex-none cursor-pointer items-center justify-center rounded-xl border transition"
      :class="isMarkup ? 'border-emerald-600 bg-emerald-50 text-emerald-600' : 'border-rose-300 bg-rose-50 text-rose-600'"
      :title="isMarkup ? 'Acréscimo (clique para virar desconto)' : 'Desconto (clique para virar acréscimo)'"
      @click="toggleSign"
    >
      <Plus v-if="isMarkup" :size="15" />
      <Minus v-else :size="15" />
    </button>
    <DiscountInput
      class="min-w-0 flex-1"
      :type="type"
      :value="magnitude"
      @update:type="emit('update:type', $event)"
      @update:value="handleMagnitudeChange"
    />
  </div>
</template>
