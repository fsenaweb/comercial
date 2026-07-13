export interface AuthUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'cashier' | 'seller'
  role_label: string
  commission_percent: string | null
  active: boolean
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
  }),

  getters: {
    isAuthenticated: (state) => state.user !== null,
    isAdmin: (state) => state.user?.role === 'admin',
    isCashier: (state) => state.user?.role === 'cashier',
  },

  actions: {
    async login(email: string, password: string) {
      await ensureCsrfCookie()

      const api = useApi()
      const { data } = await api<{ data: AuthUser }>('/login', {
        method: 'POST',
        body: { email, password },
      })

      this.user = data
    },

    async logout() {
      const api = useApi()
      await api('/logout', { method: 'POST' })
      this.user = null
    },

    async fetchCurrentUser() {
      const api = useApi()

      try {
        const { data } = await api<{ data: AuthUser }>('/me')
        this.user = data
      } catch {
        this.user = null
      }
    },
  },
})
