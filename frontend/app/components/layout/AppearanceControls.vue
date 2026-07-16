<script setup lang="ts">
import { Moon, Sun } from 'lucide-vue-next'

const preferences = usePreferencesStore()
</script>

<template>
  <div class="flex flex-none items-center gap-1">
    <div class="flex items-center rounded-full border border-border">
      <button
        type="button"
        title="Diminuir fonte"
        :disabled="preferences.fontScale === 'small'"
        class="cursor-pointer rounded-l-full px-2.5 py-1.5 text-xs font-bold text-txt-secondary hover:bg-surface-subtle hover:text-txt-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
        @click="preferences.cycleFontScale(-1)"
      >
        A-
      </button>
      <div class="h-4 w-px flex-none bg-border" />
      <button
        type="button"
        title="Aumentar fonte"
        :disabled="preferences.fontScale === 'large'"
        class="cursor-pointer rounded-r-full px-2.5 py-1.5 text-xs font-bold text-txt-secondary hover:bg-surface-subtle hover:text-txt-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
        @click="preferences.cycleFontScale(1)"
      >
        A+
      </button>
    </div>

    <button
      type="button"
      title="Alternar tema claro/escuro"
      class="flex cursor-pointer items-center justify-center rounded-full border border-border p-1.5 text-txt-secondary hover:bg-surface-subtle hover:text-txt-primary"
      @click="preferences.toggleTheme()"
    >
      <Sun v-if="preferences.theme === 'light'" :size="16" />
      <Moon v-else :size="16" />
    </button>
  </div>
</template>
