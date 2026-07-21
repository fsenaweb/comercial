import type { FontScale, Theme } from '~/utils/appearance'
import type { AuthUser } from './auth'

/**
 * Aplica tema/fonte no <html> - chamado tanto ao aplicar a preferência
 * autoritativa do usuário (após /api/me) quanto pelo hint de cookie no
 * app.head.script (nuxt.config.ts), pra evitar flash de tema errado antes
 * do Vue montar.
 */
function applyToDocument(theme: Theme, fontScale: FontScale) {
  if (import.meta.server) return

  document.documentElement.dataset.theme = theme
  document.documentElement.classList.remove('font-scale-small', 'font-scale-medium', 'font-scale-large')
  document.documentElement.classList.add(`font-scale-${fontScale}`)
}

export const usePreferencesStore = defineStore('preferences', {
  state: () => ({
    theme: 'light' as Theme,
    fontScale: 'medium' as FontScale,
  }),

  actions: {
    /** Chamado sempre que o usuário autenticado muda (login, /me, logout). */
    applyFromUser(user: AuthUser | null) {
      this.theme = user?.theme ?? 'light'
      this.fontScale = user?.font_scale ?? 'medium'

      applyToDocument(this.theme, this.fontScale)

      const themeCookie = useCookie<Theme>('ui_theme', { maxAge: 60 * 60 * 24 * 365 })
      const fontScaleCookie = useCookie<FontScale>('ui_font_scale', { maxAge: 60 * 60 * 24 * 365 })
      themeCookie.value = this.theme
      fontScaleCookie.value = this.fontScale
    },

    async update(theme: Theme, fontScale: FontScale) {
      const api = useApi()
      const { data } = await api<{ data: AuthUser }>('/me/appearance', {
        method: 'PUT',
        body: { theme, font_scale: fontScale },
      })

      const auth = useAuthStore()
      if (auth.user) {
        auth.user.theme = data.theme
        auth.user.font_scale = data.font_scale
      }

      this.applyFromUser(data)
    },

    toggleTheme() {
      return this.update(toggleTheme(this.theme), this.fontScale)
    },

    cycleFontScale(direction: 1 | -1) {
      return this.update(this.theme, nextFontScale(this.fontScale, direction))
    },
  },
})
