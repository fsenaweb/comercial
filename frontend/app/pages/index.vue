<script setup lang="ts">
const auth = useAuthStore()
</script>

<template>
  <div>
    <h1 class="font-display text-lg font-bold text-txt-primary">
      Olá, {{ auth.user?.name }}
    </h1>
    <p class="mt-2 text-sm text-txt-secondary">
      Fundação do sistema pronta. O Dashboard de vendas/estoque chega na Fase 4
      (ver docs/04-roadmap.md).
    </p>
  </div>
</template>
