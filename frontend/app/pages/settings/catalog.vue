<script setup lang="ts">
import { CreditCard, Folder, FolderTree, Ruler, Tag } from 'lucide-vue-next'

interface Category {
  id: number
  name: string
}

const categoriesApi = useResourceApi<Category>('categories')
const categories = ref<Category[]>([])

const categoryOptions = computed(() => categories.value.map((category) => ({ value: category.id, label: category.name })))

onMounted(async () => {
  categories.value = await categoriesApi.list()
})
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-txt-primary">Configurações do sistema</h1>
    <p class="mt-1 text-sm text-txt-secondary">Centralize ajustes operacionais, fiscais e administrativos.</p>

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[296px_1fr]">
      <SettingsNav />

      <div class="flex min-w-0 flex-col gap-4">
        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Área ativa</span>
          <h2 class="mt-1.5 font-display text-xl font-bold text-txt-primary">Catálogo</h2>
          <p class="mt-0.5 text-sm text-txt-secondary">Tipos, marcas, categorias e demais cadastros mestres do produto.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <CatalogEntityCard
            resource="categories"
            title="Categoria"
            title-plural="Categorias"
            description="Agrupamento principal usado para organizar o catálogo."
            :icon="Folder"
            tone="emerald"
            add-label="Nova categoria"
            :fields="[
              { key: 'name', label: 'Nome', type: 'text' },
              { key: 'description', label: 'Descrição', type: 'textarea', secondary: true },
            ]"
          />
          <CatalogEntityCard
            resource="subcategories"
            title="Subcategoria"
            title-plural="Subcategorias"
            description="Subgrupos vinculados a uma categoria existente."
            :icon="FolderTree"
            tone="amber"
            add-label="Nova subcategoria"
            :fields="[
              { key: 'category_id', label: 'Categoria', type: 'select', options: categoryOptions, secondary: true },
              { key: 'name', label: 'Nome', type: 'text' },
            ]"
          />
          <CatalogEntityCard
            resource="brands"
            title="Marca"
            title-plural="Marcas"
            description="Fabricantes e marcas dos produtos cadastrados."
            :icon="Tag"
            tone="sky"
            add-label="Nova marca"
            :fields="[{ key: 'name', label: 'Nome', type: 'text' }]"
          />
          <CatalogEntityCard
            resource="payment-methods"
            title="Forma de pagamento"
            title-plural="Formas de pagamento"
            description="Formas de pagamento aceitas no caixa e no PDV."
            :icon="CreditCard"
            tone="teal"
            add-label="Nova forma de pagamento"
            :fields="[
              { key: 'name', label: 'Nome', type: 'text' },
              { key: 'active_on_pos', label: 'Ativo no PDV', type: 'switch', secondary: true },
            ]"
          />
          <CatalogEntityCard
            resource="units"
            title="Unidade"
            title-plural="Unidades"
            description="Unidades de medida usadas na venda e no estoque (UN, CX...)."
            :icon="Ruler"
            tone="violet"
            add-label="Nova unidade"
            :fields="[
              { key: 'name', label: 'Nome', type: 'text' },
              { key: 'abbreviation', label: 'Abreviação', type: 'text', secondary: true },
            ]"
          />
        </div>
      </div>
    </div>
  </div>
</template>
