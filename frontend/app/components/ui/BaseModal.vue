<script setup lang="ts">
import { X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    subtitle?: string
    eyebrow?: string
    size?: 'md' | 'lg' | 'xl'
  }>(),
  { size: 'md' },
)

// 'lg'/'xl' usam largura relativa ao viewport (não só um max-width fixo) —
// numa tela 17"+ o modal precisa acompanhar o espaço disponível, não ficar
// travado num tamanho pensado pra tela pequena (ver docs/08-design-system.md).
const sizeClasses: Record<string, string> = {
  md: 'max-w-lg',
  lg: 'w-[85vw] max-w-[64rem]',
  xl: 'w-[90vw] max-w-[88rem]',
}

const emit = defineEmits<{ close: [] }>()

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.open) emit('close')
}

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-6" @click.self="emit('close')">
      <div
        class="my-auto flex max-h-[85vh] w-full flex-col overflow-hidden rounded-2xl bg-surface shadow-card"
        :class="sizeClasses[size]"
      >
        <div class="shrink-0 border-b border-border bg-surface-raised px-7 py-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <StatusBadge v-if="eyebrow" :label="eyebrow" tone="success" />
              <h2 class="mt-2 font-display text-xl font-bold text-txt-primary">{{ title }}</h2>
              <p v-if="subtitle" class="mt-1 text-sm text-txt-secondary">{{ subtitle }}</p>
            </div>
            <button type="button" class="shrink-0 cursor-pointer rounded-full border border-border p-1.5 text-txt-muted hover:bg-surface-subtle hover:text-txt-primary" @click="emit('close')">
              <X :size="16" />
            </button>
          </div>
        </div>

        <div class="overflow-y-auto px-7 py-6">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>
