<script setup lang="ts">
const auth = useAuthStore()

async function handleLogout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>

<template>
  <div class="min-h-screen bg-surface">
    <header class="flex items-center justify-between border-b border-border bg-surface-raised px-6 py-3">
      <span class="font-display text-lg font-bold text-txt-primary">Sistema Comercial</span>
      <div v-if="auth.user" class="flex items-center gap-4 text-sm text-txt-secondary">
        <span>{{ auth.user.name }} · {{ auth.user.role_label }}</span>
        <button
          class="rounded-xl border border-border px-3 py-1.5 font-medium text-txt-secondary transition hover:border-border-strong hover:text-txt-primary"
          @click="handleLogout"
        >
          Sair
        </button>
      </div>
    </header>

    <main class="p-6">
      <slot />
    </main>
  </div>
</template>
