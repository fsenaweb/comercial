<script setup lang="ts">
import { ArrowRight, Eye, EyeOff, Lock, Mail } from 'lucide-vue-next'

definePageMeta({ layout: false })

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const touched = ref(false)
const loading = ref(false)
const generalError = ref<string | null>(null)
const infoMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})

const auth = useAuthStore()
const { parse } = useApiError()

const emailError = computed(() => {
  if (!touched.value) return fieldErrors.value.email ?? null
  return email.value.trim() ? (fieldErrors.value.email ?? null) : 'Informe seu e-mail'
})
const passwordError = computed(() => {
  if (!touched.value) return fieldErrors.value.password ?? null
  return password.value.trim() ? (fieldErrors.value.password ?? null) : 'Informe sua senha'
})

function handleForgotPassword() {
  infoMessage.value = 'Peça ao administrador do sistema para redefinir sua senha.'
}

async function handleSubmit() {
  touched.value = true
  generalError.value = null
  infoMessage.value = null

  if (!email.value.trim() || !password.value.trim()) return

  loading.value = true
  fieldErrors.value = {}

  try {
    await auth.login(email.value, password.value)
    await navigateTo('/')
  } catch (error) {
    const parsed = parse(error)
    generalError.value = parsed.message
    fieldErrors.value = Object.fromEntries(
      Object.entries(parsed.errors ?? {}).map(([field, messages]) => [field, messages[0] ?? '']),
    )
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen">
    <div class="relative hidden w-[46%] min-w-[420px] flex-col justify-between overflow-hidden bg-txt-primary px-16 py-14 text-white lg:flex">
      <div
        class="pointer-events-none absolute inset-0 opacity-[0.03]"
        style="background-image: repeating-linear-gradient(115deg, #fff 0px, #fff 1px, transparent 1px, transparent 34px)"
      />

      <div class="relative flex flex-col gap-9">
        <div class="flex items-center gap-3.5">
          <img src="/logo.png" alt="Logo da loja" class="h-12 w-12 rounded-2xl shadow-card">
          <div class="leading-tight">
            <span class="block font-display text-lg font-bold">JP Parafusos</span>
            <span class="block text-xs font-medium text-white/60">e Acessórios</span>
          </div>
        </div>

        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-white/10 bg-white/5 py-1.5 pr-3.5 pl-2.5">
          <span class="relative flex h-1.5 w-1.5">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400" />
          </span>
          <span class="text-xs font-semibold tracking-wide text-white/85 uppercase">Loja local · rede interna</span>
        </span>
      </div>

      <div class="relative flex max-w-md flex-col gap-5">
        <h1 class="font-display text-5xl leading-[1.08] font-extrabold text-balance">
          Tem <span class="text-brand">tudo</span> em um só lugar
        </h1>
        <p class="max-w-sm text-[15px] leading-relaxed text-white/70">
          A JP Parafusos e Acessórios — soluções para sua casa, carro e oficina.
        </p>
      </div>

      <p class="relative text-xs text-white/40">© {{ new Date().getFullYear() }} JP Parafusos e Acessórios</p>
    </div>

    <div class="flex w-full items-center justify-center bg-surface px-4 lg:w-[54%]">
      <div class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center gap-3 lg:hidden">
          <img src="/logo.png" alt="Logo da loja" class="h-14 w-14 rounded-2xl shadow-card">
          <span class="font-display text-lg font-bold text-txt-primary">JP Parafusos e Acessórios</span>
        </div>

        <h2 class="font-display text-[27px] font-bold text-txt-primary">Bem-vindo de volta</h2>
        <p class="mt-1.5 text-sm text-txt-secondary">Acesse o sistema com seu usuário cadastrado.</p>

        <form class="mt-7 space-y-5" novalidate @submit.prevent="handleSubmit">
          <BaseInput
            v-model="email"
            label="E-mail"
            type="email"
            :icon="Mail"
            :error="emailError"
            autocomplete="username"
          />

          <div>
            <span class="mb-1 block text-sm font-medium text-txt-secondary">Senha</span>
            <span class="relative block">
              <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-txt-muted">
                <Lock :size="16" />
              </span>
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                class="w-full rounded-xl border border-border bg-surface-raised py-2 pr-9 pl-9 text-sm text-txt-primary transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
                :class="{ '!border-rose-500 focus:!ring-rose-500/30': passwordError }"
              >
              <button
                type="button"
                class="cursor-pointer absolute top-1/2 right-3 -translate-y-1/2 text-txt-muted hover:text-txt-secondary"
                :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                @click="showPassword = !showPassword"
              >
                <EyeOff v-if="showPassword" :size="16" />
                <Eye v-else :size="16" />
              </button>
            </span>
            <span v-if="passwordError" class="mt-1 block text-sm text-rose-600">{{ passwordError }}</span>
            <div class="mt-1.5 text-right">
              <button type="button" class="cursor-pointer text-xs font-semibold text-amber-700 hover:underline" @click="handleForgotPassword">
                Esqueceu sua senha?
              </button>
            </div>
          </div>

          <p v-if="generalError" class="text-sm text-rose-600">{{ generalError }}</p>
          <p v-else-if="infoMessage" class="text-sm text-txt-secondary">{{ infoMessage }}</p>

          <BaseButton type="submit" :loading="loading" loading-text="Entrando…">
            Entrar
            <ArrowRight :size="16" />
          </BaseButton>

          <p class="text-center text-xs text-txt-muted">Acesso restrito aos usuários cadastrados pelo administrador.</p>
        </form>
      </div>
    </div>
  </div>
</template>
