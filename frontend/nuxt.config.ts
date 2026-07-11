import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  // Sistema interno de uso local — sem necessidade de SSR/SEO (ver docs/01-architecture.md)
  ssr: false,

  app: {
    head: {
      // Tema sempre claro por decisão de produto — não seguir o SO (ver
      // docs/08-design-system.md e app/assets/css/main.css).
      meta: [{ name: 'color-scheme', content: 'light' }],
    },
  },

  modules: ['@pinia/nuxt', '@nuxt/fonts'],

  // Sem isso, um componente em components/ui/BaseButton.vue registra global
  // como <UiBaseButton> (prefixo pela subpasta), não <BaseButton> — quebrava
  // toda tela que usava <BaseButton>/<BaseInput> silenciosamente em runtime
  // (Vue não resolve o nome, renderiza só o texto do slot ou nada).
  components: [{ path: '~/components', pathPrefix: false }],

  css: ['~/assets/css/main.css'],

  vite: {
    plugins: [tailwindcss()],
  },

  // Par tipográfico do design system (ver docs/08-design-system.md) —
  // auto-hospedado pelo @nuxt/fonts (sem chamada externa ao Google Fonts).
  fonts: {
    families: [
      { name: 'Bricolage Grotesque', provider: 'google', weights: [600, 700, 800] },
      { name: 'Hanken Grotesk', provider: 'google', weights: [400, 500, 600, 700] },
    ],
  },

  runtimeConfig: {
    public: {
      // Mesma origem em produção (proxy do nginx); só diverge no `npm run dev` local.
      apiBase: '/api',
    },
  },
})
