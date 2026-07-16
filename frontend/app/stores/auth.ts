export interface AuthUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'cashier' | 'seller'
  role_label: string
  commission_percent: string | null
  active: boolean
  theme: 'light' | 'dark'
  font_scale: 'small' | 'medium' | 'large'
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
      usePreferencesStore().applyFromUser(data)
    },

    async logout() {
      const api = useApi()
      await api('/logout', { method: 'POST' })
      this.user = null
      usePreferencesStore().applyFromUser(null)
    },

    async fetchCurrentUser() {
      const api = useApi()

      try {
        const { data } = await api<{ data: AuthUser }>('/me')
        this.user = data
        usePreferencesStore().applyFromUser(data)
      } catch {
        this.user = null
      }
    },
  },
})
