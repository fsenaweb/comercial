export type PrintFormat = 'roll80' | 'roll58' | 'a4'

const state = reactive({
  open: false,
})

let resolver: ((value: PrintFormat | null) => void) | null = null

function resolvePrintFormat(value: PrintFormat | null) {
  state.open = false
  resolver?.(value)
  resolver = null
}

// Seletor de formato de impressão (Bobina 80mm/58mm/Papel A4), pedido no momento
// do clique em "Imprimir" - sem tela de configuração global (Sub-sprint D).
// Mesmo padrão singleton de useConfirmDialog: um único <PrintFormatDialogHost />
// montado em app.vue resolve todo pedido de formato do app.
function printFormatDialog(): Promise<PrintFormat | null> {
  resolver?.(null)

  state.open = true

  return new Promise((resolve) => {
    resolver = resolve
  })
}

export function usePrintFormatDialog() {
  return { state, printFormatDialog, resolvePrintFormat }
}
