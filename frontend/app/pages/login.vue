<script setup lang="ts">
definePageMeta({ layout: false })

const email = ref('')
const password = ref('')
const loading = ref(false)
const generalError = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})

const auth = useAuthStore()
const { parse } = useApiError()

async function handleSubmit() {
  loading.value = true
  generalError.value = null
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
  <div class="flex min-h-screen items-center justify-center bg-surface px-4">
    <div class="w-full max-w-sm rounded-2xl border border-border bg-surface-raised p-8 shadow-card">
      <img src="/logo.png" alt="Logo da loja" class="mx-auto mb-4 h-16 w-16 rounded-2xl shadow-card">
      <h1 class="mb-6 text-center font-display text-xl font-bold text-txt-primary">
        Sistema Comercial
      </h1>

      <form class="space-y-4" @submit.prevent="handleSubmit">
        <BaseInput
          v-model="email"
          label="E-mail"
          type="email"
          :error="fieldErrors.email"
          autocomplete="username"
        />
        <BaseInput
          v-model="password"
          label="Senha"
          type="password"
          :error="fieldErrors.password"
          autocomplete="current-password"
        />

        <p v-if="generalError" class="text-sm text-rose-600">{{ generalError }}</p>

        <BaseButton type="submit" :loading="loading">Entrar</BaseButton>
      </form>
    </div>
  </div>
</template>
