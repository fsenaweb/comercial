import { manualSections } from '~/data/manual/sections'

const state = reactive({
  open: false,
  activeSectionId: manualSections[0]!.id,
})

// Singleton, mesmo padrão de useConfirmDialog()/usePrintFormatDialog(): um
// único <ManualDialogHost /> montado em app.vue responde à tecla F1 em
// qualquer tela, sem precisar de markup próprio em cada página.
function openManual(sectionId?: string) {
  if (sectionId) state.activeSectionId = sectionId
  state.open = true
}

function closeManual() {
  state.open = false
}

function toggleManual() {
  state.open ? closeManual() : openManual()
}

function selectSection(sectionId: string) {
  state.activeSectionId = sectionId
}

export function useManualDialog() {
  return { state, openManual, closeManual, toggleManual, selectSection }
}
