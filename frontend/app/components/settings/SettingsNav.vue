<script setup lang="ts">
import { Boxes, ShieldAlert } from 'lucide-vue-next'

const route = useRoute()

const items = [
  { to: '/settings/catalog', label: 'Catálogo', description: 'Tipos, marcas, categorias e demais cadastros mestres do produto.', icon: Boxes },
]

const comingSoon = [
  { label: 'Backup e exclusão', description: 'Consulta e restauração dos backups agendados.', icon: ShieldAlert },
]

function isActive(to: string) {
  return route.path.startsWith(to)
}
</script>

<template>
  <div class="flex flex-col gap-3.5 rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
    <div>
      <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Configurações</span>
      <h3 class="mt-2 font-display text-base font-bold text-txt-primary">Painel administrativo</h3>
      <p class="mt-0.5 text-xs leading-tight text-txt-secondary">Organize catálogo, fiscal e gestão de dados sem misturar a operação.</p>
    </div>

    <nav class="flex flex-col gap-1.5">
      <NuxtLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="flex items-start gap-2.5 rounded-xl p-2.5 text-txt-secondary transition hover:bg-surface-subtle"
        :class="isActive(item.to) ? '!bg-emerald-600 !text-white shadow-card' : ''"
      >
        <span
          class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[9px]"
          :class="isActive(item.to) ? 'bg-white/20 text-white' : 'bg-surface-subtle text-txt-secondary'"
        >
          <component :is="item.icon" :size="16" />
        </span>
        <span class="min-w-0">
          <span class="block text-[13.5px] font-bold" :class="isActive(item.to) ? 'text-white' : 'text-txt-primary'">{{ item.label }}</span>
          <span class="mt-0.5 block text-[11.5px] leading-snug" :class="isActive(item.to) ? 'text-white/85' : 'text-txt-muted'">{{ item.description }}</span>
        </span>
      </NuxtLink>

      <div
        v-for="item in comingSoon"
        :key="item.label"
        title="Em breve"
        class="flex cursor-not-allowed items-start gap-2.5 rounded-xl p-2.5 text-txt-muted/70"
      >
        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[9px] bg-surface-subtle">
          <component :is="item.icon" :size="16" />
        </span>
        <span class="min-w-0">
          <span class="flex items-center gap-2 text-[13.5px] font-bold">
            {{ item.label }}
            <span class="text-[9.5px] font-bold tracking-wide uppercase opacity-70">Em breve</span>
          </span>
          <span class="mt-0.5 block text-[11.5px] leading-snug">{{ item.description }}</span>
        </span>
      </div>
    </nav>

    <div class="mt-1 flex items-start gap-2.5 rounded-2xl border border-sky-200 bg-sky-50 p-3.5">
      <ShieldAlert :size="16" class="mt-0.5 shrink-0 text-sky-600" />
      <div>
        <p class="text-xs font-bold text-sky-800">Proteção da operação</p>
        <p class="text-[11.5px] leading-snug text-sky-700">Faça backup antes de qualquer ação destrutiva.</p>
      </div>
    </div>
  </div>
</template>
