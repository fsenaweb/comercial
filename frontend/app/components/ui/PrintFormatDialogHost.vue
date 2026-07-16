<script setup lang="ts">
import { Printer } from 'lucide-vue-next'
import type { PrintFormat } from '~/composables/usePrintFormatDialog'

const { state, resolvePrintFormat } = usePrintFormatDialog()

const options: { value: PrintFormat, label: string, hint: string }[] = [
  { value: 'roll80', label: 'Bobina 80mm', hint: 'Impressora térmica padrão' },
  { value: 'roll58', label: 'Bobina 58mm', hint: 'Impressora térmica compacta' },
  { value: 'a4', label: 'Papel A4', hint: 'Impressora comum' },
]
</script>

<template>
  <BaseModal :open="state.open" title="Formato de impressão" subtitle="Escolha o formato para esta impressão" @close="resolvePrintFormat(null)">
    <div class="flex flex-col gap-2.5">
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        class="flex w-full items-center gap-3 rounded-xl border border-border p-4 text-left transition hover:border-brand hover:bg-surface-subtle"
        @click="resolvePrintFormat(option.value)"
      >
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-surface-subtle text-txt-secondary">
          <Printer :size="18" />
        </span>
        <span>
          <span class="block text-sm font-semibold text-txt-primary">{{ option.label }}</span>
          <span class="block text-xs text-txt-muted">{{ option.hint }}</span>
        </span>
      </button>
    </div>
  </BaseModal>
</template>
