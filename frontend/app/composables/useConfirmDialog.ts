interface ConfirmOptions {
  title?: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'primary' | 'danger'
}

const state = reactive({
  open: false,
  title: 'Confirmar ação',
  message: '',
  confirmLabel: 'Confirmar',
  cancelLabel: 'Cancelar',
  variant: 'primary' as 'primary' | 'danger',
})

let resolver: ((value: boolean) => void) | null = null

function resolveConfirm(value: boolean) {
  state.open = false
  resolver?.(value)
  resolver = null
}

// Substitui o `confirm()` nativo do navegador por um modal do design system.
// Singleton: um único <ConfirmDialogHost /> montado em app.vue resolve todo
// pedido de confirmação do app, sem precisar de markup próprio em cada tela.
function confirmDialog(options: ConfirmOptions | string): Promise<boolean> {
  const opts = typeof options === 'string' ? { message: options } : options

  // Um confirmDialog() chamado antes do anterior resolver (não deveria
  // acontecer via clique, mas evita vazar a Promise pendente) cancela o
  // anterior como "não confirmado".
  resolver?.(false)

  state.title = opts.title ?? 'Confirmar ação'
  state.message = opts.message
  state.confirmLabel = opts.confirmLabel ?? 'Confirmar'
  state.cancelLabel = opts.cancelLabel ?? 'Cancelar'
  state.variant = opts.variant ?? 'primary'
  state.open = true

  return new Promise((resolve) => {
    resolver = resolve
  })
}

export function useConfirmDialog() {
  return { state, confirmDialog, resolveConfirm }
}
