<script setup lang="ts">
import { Building2, Check, CircleCheck, ImageIcon, MapPin, Phone, Upload } from 'lucide-vue-next'

interface StoreSettings {
  name: string
  trade_name: string | null
  cnpj: string | null
  email: string | null
  phone: string | null
  mobile_phone: string | null
  zip_code: string | null
  address: string | null
  address_number: string | null
  address_complement: string | null
  neighborhood: string | null
  city: string | null
  state: string | null
  logo_path: string | null
  logo_url: string | null
  require_seller_on_sale: boolean
  auto_open_cash_register: boolean
}

const ufOptions = [
  'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB',
  'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
].map((uf) => ({ value: uf, label: uf }))

const api = useApi()
const { parse, firstFieldError } = useApiError()
const { maskInput: maskDocument } = useDocumentMask()
const { maskInput: maskCep } = useCepMask()
const { maskInput: maskPhone } = usePhoneMask()

function emptyForm(): StoreSettings {
  return {
    name: '',
    trade_name: null,
    cnpj: null,
    email: null,
    phone: null,
    mobile_phone: null,
    zip_code: null,
    address: null,
    address_number: null,
    address_complement: null,
    neighborhood: null,
    city: null,
    state: null,
    logo_path: null,
    logo_url: null,
    require_seller_on_sale: false,
    auto_open_cash_register: false,
  }
}

const form = reactive<StoreSettings>(emptyForm())

async function fetchSettings() {
  const res = await api<{ data: StoreSettings }>('/store-settings')
  Object.assign(form, res.data)
}

await fetchSettings()

const baseFieldsFilled = computed(() => [form.name, form.cnpj, form.phone || form.mobile_phone, form.zip_code].filter(Boolean).length)

const saving = ref(false)
const reloading = ref(false)
const message = ref<string | null>(null)
const error = ref<unknown>(null)

async function handleSubmit() {
  saving.value = true
  message.value = null
  error.value = null

  try {
    const res = await api<{ data: StoreSettings }>('/store-settings', { method: 'PUT', body: form })
    Object.assign(form, res.data)
    message.value = 'Dados da empresa salvos.'
  } catch (err) {
    error.value = err
  } finally {
    saving.value = false
  }
}

async function handleReload() {
  reloading.value = true
  message.value = null
  error.value = null

  try {
    await fetchSettings()
  } finally {
    reloading.value = false
  }
}

function handleDocumentInput(value: string) {
  form.cnpj = maskDocument(value, 'cnpj')
}

function handleZipCodeInput(value: string) {
  form.zip_code = maskCep(value)
}

function handlePhoneInput(value: string) {
  form.phone = maskPhone(value, 'landline')
}

function handleMobilePhoneInput(value: string) {
  form.mobile_phone = maskPhone(value, 'mobile')
}

// ---- Logotipo ----

const logoInput = ref<HTMLInputElement | null>(null)
const logoUploading = ref(false)
const logoError = ref<string | null>(null)

function openLogoPicker() {
  logoInput.value?.click()
}

async function handleLogoChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  logoError.value = null

  if (!['image/png', 'image/jpeg'].includes(file.type)) {
    logoError.value = 'Envie o logotipo em PNG ou JPG.'
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    logoError.value = 'O logotipo deve ter até 2MB.'
    return
  }

  const body = new FormData()
  body.append('logo', file)

  logoUploading.value = true
  try {
    const res = await api<{ data: StoreSettings }>('/store-settings/logo', { method: 'POST', body })
    form.logo_path = res.data.logo_path
    form.logo_url = res.data.logo_url
  } catch (err) {
    logoError.value = parse(err).message
  } finally {
    logoUploading.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- HERO -->
    <div class="flex flex-wrap items-start justify-between gap-6 rounded-2xl border border-border bg-surface-raised p-7 shadow-card">
      <div>
        <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Identidade da empresa</span>
        <h1 class="mt-1.5 font-display text-2xl font-bold text-txt-primary">Dados da empresa</h1>
        <p class="mt-1 max-w-lg text-sm leading-relaxed text-txt-secondary">
          Mantenha os dados cadastrais, o contato e a identidade visual da empresa sempre alinhados com a sua operação.
        </p>

        <div class="mt-5 flex flex-wrap gap-3.5">
          <div class="min-w-[150px] rounded-2xl border border-border bg-surface px-4.5 py-3.5">
            <span class="text-[10px] font-bold tracking-wide text-txt-muted uppercase">Cadastro base</span>
            <p class="mt-1 font-display text-base font-bold text-txt-primary">{{ baseFieldsFilled }}/4 campos-chave</p>
          </div>
        </div>
      </div>

      <div class="flex w-[280px] flex-none flex-col gap-2.5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
        <div class="flex items-center gap-2">
          <CircleCheck :size="16" class="shrink-0 text-emerald-700" />
          <span class="text-[12.5px] font-bold text-emerald-900">Boas práticas</span>
        </div>
        <ul class="flex flex-col gap-2">
          <li class="text-xs leading-relaxed text-emerald-800">Use dados consistentes aqui para refletir corretamente em relatórios e na comunicação com o cliente.</li>
          <li class="text-xs leading-relaxed text-emerald-800">Mantenha e-mail e telefone atualizados para suporte e documentos.</li>
          <li class="text-xs leading-relaxed text-emerald-800">Prefira logotipo com fundo transparente para recibos, cabeçalhos e relatórios.</li>
        </ul>
      </div>
    </div>

    <!-- MARCA + DADOS PRINCIPAIS -->
    <div class="grid items-start gap-4 lg:grid-cols-[320px_1fr]">
      <div class="flex flex-col gap-4 rounded-2xl border border-border bg-surface-raised p-5.5 shadow-card">
        <div class="flex gap-3">
          <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
            <ImageIcon :size="17" />
          </span>
          <div>
            <span class="text-[10.5px] font-bold tracking-wide text-emerald-700 uppercase">Marca</span>
            <p class="font-display text-base font-bold text-txt-primary">Identidade visual</p>
            <p class="mt-0.5 text-xs leading-relaxed text-txt-secondary">Sua imagem aparece em vários pontos da operação, como recibos e telas internas.</p>
          </div>
        </div>

        <div class="flex justify-center py-1">
          <span class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-full border border-border bg-surface-subtle">
            <img v-if="form.logo_url" :src="form.logo_url" alt="Logotipo da loja" class="h-full w-full object-cover">
            <Building2 v-else :size="42" class="text-txt-muted" />
          </span>
        </div>

        <input ref="logoInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="handleLogoChange">
        <BaseButton type="button" :loading="logoUploading" @click="openLogoPicker">
          <Upload :size="15" />
          Trocar logotipo
        </BaseButton>
        <p v-if="logoError" class="text-center text-xs text-rose-600">{{ logoError }}</p>
        <span v-else class="text-center text-[11.5px] text-txt-muted">PNG ou JPG até 2MB. De preferência em fundo transparente.</span>
      </div>

      <div class="flex flex-col gap-4 rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
        <div class="flex gap-3">
          <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
            <Building2 :size="17" />
          </span>
          <div>
            <span class="text-[10.5px] font-bold tracking-wide text-emerald-700 uppercase">Cadastro</span>
            <p class="font-display text-base font-bold text-txt-primary">Dados principais</p>
            <p class="mt-0.5 text-xs leading-relaxed text-txt-secondary">Informações centrais que identificam a empresa dentro do sistema.</p>
          </div>
        </div>

        <div class="border-t border-border" />

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="form.name" label="Razão social" placeholder="Ex: JP Parafusos e Acessórios Ltda" :error="firstFieldError(error, 'name')" />
          <BaseInput v-model="form.trade_name" label="Nome fantasia" placeholder="Ex: JP Parafusos" :error="firstFieldError(error, 'trade_name')" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput :model-value="form.cnpj" label="CNPJ" placeholder="99.999.999/9999-99" :error="firstFieldError(error, 'cnpj')" @update:model-value="handleDocumentInput" />
          <BaseInput v-model="form.email" type="email" label="E-mail" placeholder="Ex: contato@jpparafusos.com.br" :error="firstFieldError(error, 'email')" />
        </div>
      </div>
    </div>

    <!-- CONTATO -->
    <div class="flex flex-col gap-4 rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
      <div class="flex gap-3">
        <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
          <Phone :size="17" />
        </span>
        <div>
          <span class="text-[10.5px] font-bold tracking-wide text-emerald-700 uppercase">Contato</span>
          <p class="font-display text-base font-bold text-txt-primary">Canais da empresa</p>
          <p class="mt-0.5 text-xs leading-relaxed text-txt-secondary">Esses dados ajudam no atendimento, na comunicação comercial e em documentos.</p>
        </div>
      </div>

      <div class="border-t border-border" />

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseInput :model-value="form.phone" label="Telefone" placeholder="Ex: (41) 3333-3333" :error="firstFieldError(error, 'phone')" @update:model-value="handlePhoneInput" />
        <BaseInput :model-value="form.mobile_phone" label="Celular (WhatsApp)" placeholder="Ex: (41) 9 9999-9999" :error="firstFieldError(error, 'mobile_phone')" @update:model-value="handleMobilePhoneInput" />
      </div>
    </div>

    <!-- ENDEREÇO -->
    <div class="flex flex-col gap-4 rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
      <div class="flex gap-3">
        <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
          <MapPin :size="17" />
        </span>
        <div>
          <span class="text-[10.5px] font-bold tracking-wide text-emerald-700 uppercase">Endereço</span>
          <p class="font-display text-base font-bold text-txt-primary">Localização da empresa</p>
          <p class="mt-0.5 text-xs leading-relaxed text-txt-secondary">Usado em documentos e no cabeçalho de comprovantes internos.</p>
        </div>
      </div>

      <div class="border-t border-border" />

      <div class="grid gap-4 sm:grid-cols-[1fr_2.4fr_1fr]">
        <BaseInput :model-value="form.zip_code" label="CEP" placeholder="Ex: 99999-999" :error="firstFieldError(error, 'zip_code')" @update:model-value="handleZipCodeInput" />
        <BaseInput v-model="form.address" label="Rua" placeholder="Ex: Rua 15 de Março" :error="firstFieldError(error, 'address')" />
        <BaseInput v-model="form.address_number" label="Número" placeholder="836" :error="firstFieldError(error, 'address_number')" />
      </div>

      <div class="grid gap-4 sm:grid-cols-[1fr_1fr_1.2fr]">
        <BaseInput v-model="form.address_complement" label="Complemento" placeholder="Ex: Sala 2" :error="firstFieldError(error, 'address_complement')" />
        <BaseInput v-model="form.neighborhood" label="Bairro" placeholder="Ex: Centro" :error="firstFieldError(error, 'neighborhood')" />
        <BaseInput v-model="form.city" label="Cidade" placeholder="Ex: Curitiba" :error="firstFieldError(error, 'city')" />
      </div>

      <div class="max-w-[180px]">
        <BaseSelect v-model="form.state" label="UF" :options="ufOptions" placeholder="Selecione" :error="firstFieldError(error, 'state')" />
      </div>
    </div>

    <!-- COMPORTAMENTO DO PDV -->
    <div class="flex flex-col gap-4 rounded-2xl border border-border bg-surface-raised p-6 shadow-card">
      <div>
        <span class="text-[10.5px] font-bold tracking-wide text-emerald-700 uppercase">Operação</span>
        <p class="font-display text-base font-bold text-txt-primary">Comportamento do PDV</p>
      </div>

      <div class="border-t border-border" />

      <BaseSwitch v-model="form.require_seller_on_sale" label="Exigir vendedor na venda" />
      <BaseSwitch v-model="form.auto_open_cash_register" label="Abrir caixa automaticamente" />
    </div>

    <!-- SAVE BAR -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
      <div>
        <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Pronto para salvar</span>
        <p class="mt-1 text-xs text-txt-secondary">Revise os dados antes de confirmar. As alterações refletem em telas internas e comprovantes.</p>
        <p v-if="message" class="mt-1 text-sm font-semibold text-emerald-600">{{ message }}</p>
        <p v-if="error" class="mt-1 text-sm text-rose-600">{{ parse(error).message }}</p>
      </div>
      <div class="flex gap-3">
        <BaseButton type="button" variant="ghost" :block="false" :loading="reloading" @click="handleReload">Recarregar dados</BaseButton>
        <BaseButton type="button" :block="false" :loading="saving" @click="handleSubmit">
          <Check :size="15" />
          Salvar alterações
        </BaseButton>
      </div>
    </div>
  </div>
</template>
