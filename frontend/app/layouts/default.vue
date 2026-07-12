<script setup lang="ts">
const auth = useAuthStore()
</script>

<template>
  <div class="flex h-screen bg-surface">
    <AppSidebar v-if="auth.user" />

    <div class="flex min-w-0 flex-1 flex-col">
      <AppHeader v-if="auth.user" />

      <main class="flex-1 overflow-y-auto p-8">
        <slot />
      </main>

      <AppFooter v-if="auth.user" />
    </div>
  </div>
</template>
