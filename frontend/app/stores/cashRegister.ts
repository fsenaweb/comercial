export interface CashRegister {
  id: number
  opened_at: string
  opening_amount: string
  status: 'open' | 'closed'
  status_label: string
  closed_at: string | null
  closing_amount: string | null
  expected_amount: string
  difference_amount: string | null
  opened_by: number
  opened_by_name: string | null
  closed_by: number | null
  closed_by_name: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export type CashOperationOrigin = 'sale' | 'cash_withdrawal' | 'cash_reinforcement' | 'adjustment' | 'accounts_receivable'

export interface CashOperation {
  id: number
  cash_register_id: number
  user_id: number
  user_name: string | null
  type: 'in' | 'out'
  type_label: string
  origin: CashOperationOrigin
  origin_label: string
  reference_id: number | null
  payment_method_id: number | null
  payment_method_name: string | null
  amount: string
  notes: string | null
  sale_number: string | null
  sale_status: 'pending' | 'completed' | 'canceled' | null
  sale_status_label: string | null
  created_at: string
}

export const useCashRegisterStore = defineStore('cashRegister', {
  state: () => ({
    registers: [] as CashRegister[],
    current: null as CashRegister | null,
    operations: [] as CashOperation[],
  }),

  getters: {
    isOpen: (state) => state.current?.status === 'open',
  },

  actions: {
    async fetchList(params?: { status?: string; search?: string }) {
      const api = useApi()
      const query = new URLSearchParams()
      if (params?.status) query.set('status', params.status)
      if (params?.search) query.set('search', params.search)
      const qs = query.toString()

      const { data } = await api<{ data: CashRegister[] }>(`/cash-registers${qs ? `?${qs}` : ''}`)
      this.registers = data
      return data
    },

    async fetchCurrent() {
      const api = useApi()
      const { data } = await api<{ data: CashRegister | null }>('/cash-registers/current')
      this.current = data
      return data
    },

    async open(openingAmount: number, notes?: string | null) {
      const api = useApi()
      const { data } = await api<{ data: CashRegister }>('/cash-registers/open', {
        method: 'POST',
        body: { opening_amount: openingAmount, notes },
      })
      this.current = data
      return data
    },

    async update(id: number, openingAmount: number, notes?: string | null) {
      const api = useApi()
      const { data } = await api<{ data: CashRegister }>(`/cash-registers/${id}`, {
        method: 'PUT',
        body: { opening_amount: openingAmount, notes },
      })
      if (this.current?.id === id) this.current = data
      return data
    },

    async close(id: number, closingAmount: number, notes?: string | null) {
      const api = useApi()
      const { data } = await api<{ data: CashRegister }>(`/cash-registers/${id}/close`, {
        method: 'POST',
        body: { closing_amount: closingAmount, notes },
      })
      if (this.current?.id === id) this.current = null
      return data
    },

    async fetchOperations(cashRegisterId: number) {
      const api = useApi()
      const { data } = await api<{ data: CashOperation[] }>(`/cash-registers/${cashRegisterId}/operations`)
      this.operations = data
      return data
    },

    async registerOperation(origin: 'cash_withdrawal' | 'cash_reinforcement', amount: number, paymentMethodId?: string | number | null, notes?: string | null) {
      const api = useApi()
      const { data } = await api<{ data: CashOperation }>('/cash-registers/operations', {
        method: 'POST',
        body: { origin, amount, payment_method_id: paymentMethodId, notes },
      })
      this.operations = [data, ...this.operations]
      return data
    },

    async removeOperation(operationId: number) {
      const api = useApi()
      await api(`/cash-registers/operations/${operationId}`, { method: 'DELETE' })
      this.operations = this.operations.filter((operation) => operation.id !== operationId)
    },
  },
})
