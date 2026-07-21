<script setup lang="ts">
import { ChevronLeft, ChevronRight, LogOut } from 'lucide-vue-next'
import { manualSectionsForRole } from '~/data/manual/sections'

const { state, closeManual, selectSection } = useManualDialog()
const auth = useAuthStore()

const visibleSections = computed(() => manualSectionsForRole(auth.user?.role))

const activeIndex = computed(() => visibleSections.value.findIndex((section) => section.id === state.activeSectionId))
const activeSection = computed(() => visibleSections.value[activeIndex.value] ?? visibleSections.value[0])
const hasPrev = computed(() => activeIndex.value > 0)
const hasNext = computed(() => activeIndex.value >= 0 && activeIndex.value < visibleSections.value.length - 1)

function goPrev() {
  if (hasPrev.value) selectSection(visibleSections.value[activeIndex.value - 1]!.id)
}

function goNext() {
  if (hasNext.value) selectSection(visibleSections.value[activeIndex.value + 1]!.id)
}

// F4 abre/fecha o manual em qualquer tela (padrão de ajuda de aplicativo
// desktop, pedido do usuário) - preventDefault() pra não disputar com
// atalhos do navegador. F1 foi descartado: em muitas máquinas Windows é
// interceptado pelo fabricante do notebook/teclado ou por política de TI
// antes mesmo de chegar à página, fora do alcance de qualquer
// preventDefault() no JS. Esc já é tratado pelo próprio BaseModal.
function handleGlobalKeydown(event: KeyboardEvent) {
  if (event.key !== 'F4') return
  event.preventDefault()
  state.open ? closeManual() : (state.open = true)
}

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown))
</script>

<template>
  <BaseModal
    :open="state.open"
    title="Manual do Usuário"
    subtitle="Pressione F4 a qualquer momento para abrir ou fechar este manual."
    size="xl"
    @close="closeManual"
  >
    <div class="flex flex-col gap-6 md:flex-row">
      <nav class="flex flex-row flex-wrap gap-2 md:w-56 md:flex-none md:flex-col">
        <button
          v-for="section in visibleSections"
          :key="section.id"
          type="button"
          class="flex cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition"
          :class="section.id === activeSection?.id
            ? 'bg-brand text-brand-ink'
            : 'text-txt-secondary hover:bg-surface-subtle hover:text-txt-primary'"
          @click="selectSection(section.id)"
        >
          <component :is="section.icon" :size="16" class="shrink-0" />
          <span>{{ section.title }}</span>
        </button>
      </nav>

      <div class="min-w-0 flex-1">
        <component :is="activeSection.component" v-if="activeSection" />
      </div>
    </div>

    <div class="mt-6 flex items-center justify-between border-t border-border pt-4">
      <BaseButton variant="ghost" :block="false" :disabled="!hasPrev" @click="goPrev">
        <ChevronLeft :size="14" />
        Anterior
      </BaseButton>

      <BaseButton variant="ghost" :block="false" @click="closeManual">
        <LogOut :size="14" />
        Sair
      </BaseButton>

      <BaseButton variant="ghost" :block="false" :disabled="!hasNext" @click="goNext">
        Próxima
        <ChevronRight :size="14" />
      </BaseButton>
    </div>
  </BaseModal>
</template>
