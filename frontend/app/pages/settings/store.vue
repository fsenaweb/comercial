<script setup lang="ts">
interface StoreSettings {
  name: string
  cnpj: string | null
  address: string | null
  phone: string | null
  logo_path: string | null
  require_seller_on_sale: boolean
  auto_open_cash_register: boolean
}

const api = useApi()
const { parse } = useApiError()

const { data } = await useAsyncData('store-settings', () =>
  api<{ data: StoreSettings }>('/store-settings').then((res) => res.data),
)

const form = reactive<StoreSettings>({
  name: data.value?.name ?? '',
  cnpj: data.value?.cnpj ?? null,
  address: data.value?.address ?? null,
  phone: data.value?.phone ?? null,
  logo_path: data.value?.logo_path ?? null,
  require_seller_on_sale: data.value?.require_seller_on_sale ?? false,
  auto_open_cash_register: data.value?.auto_open_cash_register ?? false,
})

const saving = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

async function handleSubmit() {
  saving.value = true
  message.value = null
  error.value = null

  try {
    await api('/store-settings', { method: 'PUT', body: form })
    message.value = 'Configurações salvas.'
  } catch (err) {
    error.value = parse(err).message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-lg">
    <h1 class="mb-6 font-display text-lg font-bold text-txt-primary">Configuração da loja</h1>

    <div class="rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
      <form class="space-y-4" @submit.prevent="handleSubmit">
        <p class="text-xs font-semibold tracking-wide text-txt-muted uppercase">Dados principais</p>
        <BaseInput v-model="form.name" label="Nome da loja" />
        <BaseInput v-model="form.cnpj" label="CNPJ" />
        <BaseInput v-model="form.address" label="Endereço" />
        <BaseInput v-model="form.phone" label="Telefone" />

        <p class="pt-2 text-xs font-semibold tracking-wide text-txt-muted uppercase">Comportamento do PDV</p>
        <label class="flex items-center gap-2 text-sm text-txt-secondary">
          <input v-model="form.require_seller_on_sale" type="checkbox" class="accent-brand">
          Exigir vendedor na venda
        </label>

        <label class="flex items-center gap-2 text-sm text-txt-secondary">
          <input v-model="form.auto_open_cash_register" type="checkbox" class="accent-brand">
          Abrir caixa automaticamente
        </label>

        <p v-if="message" class="text-sm text-emerald-600">{{ message }}</p>
        <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

        <BaseButton type="submit" :loading="saving">Salvar</BaseButton>
      </form>
    </div>
  </div>
</template>
