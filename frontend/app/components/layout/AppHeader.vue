<script setup lang="ts">
import { ChevronDown, LogOut, Search, Settings } from 'lucide-vue-next'

const auth = useAuthStore()
const menuOpen = ref(false)

async function handleLogout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>

<template>
  <header class="flex items-center justify-between border-b border-border bg-surface-raised px-8 py-3.5">
    <label class="flex w-full max-w-xs items-center gap-2 rounded-full border border-border bg-surface px-3.5 py-2 text-sm text-txt-muted">
      <Search :size="15" />
      <input type="search" placeholder="Buscar..." class="w-full bg-transparent text-txt-primary placeholder:text-txt-muted focus:outline-none">
    </label>

    <div v-if="auth.user" class="flex items-center gap-3">
      <AppearanceControls />

      <div class="relative">
        <button type="button" class="flex items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-surface-subtle" @click="menuOpen = !menuOpen">
          <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand font-display text-sm font-bold text-brand-ink"
          >
            {{ auth.user.name.charAt(0) }}
          </span>
          <span class="hidden text-left leading-tight sm:block">
            <span class="block text-sm font-bold text-txt-primary">{{ auth.user.name }}</span>
            <span class="block text-xs font-medium text-txt-muted">{{ auth.user.role_label }}</span>
          </span>
          <ChevronDown :size="14" class="text-txt-muted transition-transform" :class="{ 'rotate-180': menuOpen }" />
        </button>

        <div
          v-if="menuOpen"
          class="absolute top-14 right-0 z-10 w-48 rounded-2xl border border-border bg-surface-raised p-2 shadow-card"
          @click="menuOpen = false"
        >
          <NuxtLink
            v-if="auth.isAdmin"
            to="/settings/store"
            class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold text-txt-secondary hover:bg-surface-subtle"
          >
            <Settings :size="16" />
            Configurações
          </NuxtLink>
          <div class="my-1 h-px bg-border" />
          <button
            type="button"
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50"
            @click="handleLogout"
          >
            <LogOut :size="16" />
            Sair do sistema
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
