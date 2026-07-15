<script setup lang="ts">
import { AlertTriangle, FileUp, Pencil, Plus, Search, Trash2, Truck, Upload } from 'lucide-vue-next'

interface Supplier {
  id: number
  corporate_name: string
  trade_name: string | null
  mobile_phone: string | null
  phone: string | null
  email: string | null
  document: string | null
  is_company: boolean
  state_registration: string | null
  address: string | null
  zip_code: string | null
  address_number: string | null
  address_complement: string | null
  neighborhood: string | null
  city: string | null
  state: string | null
  notes: string | null
}

const ufOptions = [
  'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB',
  'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
].map((uf) => ({ value: uf, label: uf }))

const api = useResourceApi<Supplier>('suppliers')
const { parse, firstFieldError } = useApiError()
const { maskInput: maskDocument } = useDocumentMask()
const { maskInput: maskCep } = useCepMask()
const { maskInput: maskPhone } = usePhoneMask()
const auth = useAuthStore()

const suppliers = ref<Supplier[]>([])
const loading = ref(true)
const search = ref('')

async function load() {
  loading.value = true
  suppliers.value = await api.list()
  loading.value = false
}

const filteredSuppliers = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return suppliers.value
  return suppliers.value.filter(
    (s) => s.corporate_name.toLowerCase().includes(query) || s.document?.toLowerCase().includes(query),
  )
})

// ---- Modal "Novo Fornecedor" / "Editar Fornecedor" ----

const modalOpen = ref(false)
const modalSaving = ref(false)
const modalError = ref<unknown>(null)
const editingId = ref<number | null>(null)

function emptyForm() {
  return {
    corporate_name: '',
    trade_name: null as string | null,
    mobile_phone: null as string | null,
    phone: null as string | null,
    email: null as string | null,
    document: null as string | null,
    // Fornecedor nasce como Pessoa Jurídica (maioria dos casos) — o inverso de
    // Clientes, que nasce Pessoa Física; o usuário desmarca se for autônomo/PF.
    is_company: true,
    state_registration: null as string | null,
    address: null as string | null,
    zip_code: null as string | null,
    address_number: null as string | null,
    address_complement: null as string | null,
    neighborhood: null as string | null,
    city: null as string | null,
    state: null as string | null,
    notes: null as string | null,
  }
}

const form = reactive(emptyForm())

function openCreateModal() {
  editingId.value = null
  Object.assign(form, emptyForm())
  modalError.value = null
  modalOpen.value = true
}

function openEditModal(supplier: Supplier) {
  editingId.value = supplier.id
  Object.assign(form, supplier)
  modalError.value = null
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
}

function handleDocumentInput(value: string) {
  form.document = maskDocument(value, form.is_company ? 'cnpj' : 'cpf')
}

function handleMobilePhoneInput(value: string) {
  form.mobile_phone = maskPhone(value, 'mobile')
}

function handlePhoneInput(value: string) {
  form.phone = maskPhone(value, 'landline')
}

function handleZipCodeInput(value: string) {
  form.zip_code = maskCep(value)
}

async function handleSubmit() {
  modalSaving.value = true
  modalError.value = null

  try {
    if (editingId.value) {
      await api.update(editingId.value, form)
    } else {
      await api.create(form)
    }
    closeModal()
    await load()
  } catch (err) {
    modalError.value = err
  } finally {
    modalSaving.value = false
  }
}

const { confirmDialog } = useConfirmDialog()

async function handleModalDelete() {
  if (!editingId.value) return
  const supplier = suppliers.value.find((s) => s.id === editingId.value)
  if (!supplier) return
  const confirmed = await confirmDialog({
    title: 'Excluir fornecedor',
    message: `Excluir o fornecedor "${supplier.corporate_name}"?`,
    confirmLabel: 'Excluir',
    variant: 'danger',
  })
  if (!confirmed) return

  await api.remove(editingId.value)
  closeModal()
  await load()
}

// ---- Modal "Importar planilha" — UI presente, processamento ainda não existe ----
const importOpen = ref(false)

await load()
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Fornecedores</h1>
      <p class="text-sm text-txt-secondary">Cadastro e organização dos fornecedores usados nas compras.</p>
    </div>

    <StatCard label="Fornecedores" :value="suppliers.length" subtext="cadastros encontrados" :icon="Truck" tone="violet" class="max-w-xs" />

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card">
      <BaseButton :block="false" @click="openCreateModal">
        <Plus :size="15" />
        Novo Fornecedor
      </BaseButton>
      <BaseButton variant="ghost" :block="false" @click="importOpen = true">
        <Upload :size="15" />
        IMPORTAR PLANILHA
      </BaseButton>
      <div class="flex-1" />
      <label class="flex w-full max-w-xs items-center gap-2 rounded-full border border-border bg-surface px-3.5 py-2 text-txt-muted">
        <input v-model="search" type="text" placeholder="Nome ou CNPJ/CPF" class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none">
        <Search :size="15" />
      </label>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
      <div class="grid grid-cols-[1.6fr_0.8fr_1fr_1.3fr_1fr_60px] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
        <span>Nome</span>
        <span>Tipo</span>
        <span>Celular</span>
        <span>E-mail</span>
        <span>CNPJ/CPF</span>
        <span class="text-right">Ações</span>
      </div>

      <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="filteredSuppliers.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">
        Nenhum fornecedor cadastrado ainda.
      </div>
      <div
        v-for="supplier in filteredSuppliers"
        v-else
        :key="supplier.id"
        class="grid grid-cols-[1.6fr_0.8fr_1fr_1.3fr_1fr_60px] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
      >
        <span class="text-sm font-medium text-txt-primary">{{ supplier.corporate_name }}</span>
        <span>
          <StatusBadge :label="supplier.is_company ? 'PJ' : 'PF'" tone="info" />
        </span>
        <span class="text-sm text-txt-secondary">{{ supplier.mobile_phone ?? '—' }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ supplier.email ?? '—' }}</span>
        <span class="text-sm text-txt-secondary">{{ supplier.document ?? '—' }}</span>
        <div class="flex justify-end">
          <IconButton :icon="Pencil" label="Editar" @click="openEditModal(supplier)" />
        </div>
      </div>

      <div class="flex items-center justify-end border-t border-border px-5 py-3.5">
        <span class="text-xs text-txt-secondary">
          Exibindo <strong class="text-txt-primary">{{ filteredSuppliers.length }}</strong> de
          <strong class="text-txt-primary">{{ suppliers.length }}</strong>
        </span>
      </div>
    </div>

    <BaseModal
      :open="modalOpen"
      size="lg"
      :title="editingId ? 'Editar fornecedor' : 'Novo fornecedor'"
      subtitle="Cadastre ou atualize os dados do fornecedor para organizar suas compras."
      @close="closeModal"
    >
      <form class="space-y-5" @submit.prevent="handleSubmit">
        <p class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Dados principais</p>
        <BaseInput v-model="form.corporate_name" label="Nome/Razão Social" :error="firstFieldError(modalError, 'corporate_name')" />
        <div class="grid grid-cols-2 gap-4">
          <BaseInput :model-value="form.mobile_phone" label="Celular" :error="firstFieldError(modalError, 'mobile_phone')" @update:model-value="handleMobilePhoneInput" />
          <BaseInput :model-value="form.phone" label="Telefone" :error="firstFieldError(modalError, 'phone')" @update:model-value="handlePhoneInput" />
        </div>
        <BaseInput v-model="form.email" label="E-mail" :error="firstFieldError(modalError, 'email')" />

        <div class="h-px bg-border" />

        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Documentos</p>
          <BaseSwitch v-model="form.is_company" label="Pessoa Jurídica (CNPJ)" />
        </div>
        <div class="grid grid-cols-3 gap-4">
          <BaseInput v-model="form.trade_name" label="Nome fantasia" :error="firstFieldError(modalError, 'trade_name')" />
          <BaseInput
            :model-value="form.document"
            :label="form.is_company ? 'CNPJ' : 'CPF'"
            :error="firstFieldError(modalError, 'document')"
            @update:model-value="handleDocumentInput"
          />
          <BaseInput v-model="form.state_registration" :label="form.is_company ? 'IE' : 'RG'" :error="firstFieldError(modalError, 'state_registration')" />
        </div>
        <BaseTextarea v-model="form.notes" label="Observações" :error="firstFieldError(modalError, 'notes')" />

        <div class="h-px bg-border" />

        <p class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Endereço</p>
        <div class="max-w-[220px]">
          <BaseInput :model-value="form.zip_code" label="CEP" :error="firstFieldError(modalError, 'zip_code')" @update:model-value="handleZipCodeInput" />
        </div>
        <div class="grid grid-cols-[2.2fr_1fr] gap-4">
          <BaseInput v-model="form.address" label="Endereço" :error="firstFieldError(modalError, 'address')" />
          <BaseInput v-model="form.address_number" label="Número" :error="firstFieldError(modalError, 'address_number')" />
        </div>
        <div class="grid grid-cols-3 gap-4">
          <BaseInput v-model="form.address_complement" label="Complemento" :error="firstFieldError(modalError, 'address_complement')" />
          <BaseInput v-model="form.neighborhood" label="Bairro" :error="firstFieldError(modalError, 'neighborhood')" />
          <BaseInput v-model="form.city" label="Cidade" :error="firstFieldError(modalError, 'city')" />
        </div>
        <div class="max-w-[220px]">
          <BaseSelect v-model="form.state" label="Estado" :options="ufOptions" placeholder="Selecione o estado" :error="firstFieldError(modalError, 'state')" />
        </div>

        <p v-if="modalError" class="text-sm text-rose-600">{{ parse(modalError).message }}</p>

        <div class="flex items-center justify-between gap-3 border-t border-border pt-4">
          <BaseButton v-if="editingId && auth.isAdmin" type="button" variant="danger" :block="false" @click="handleModalDelete">
            <Trash2 :size="15" />
            Excluir fornecedor
          </BaseButton>
          <div v-else />
          <div class="flex gap-3">
            <BaseButton type="button" variant="ghost" :block="false" @click="closeModal">Cancelar</BaseButton>
            <BaseButton type="submit" :loading="modalSaving" :block="false">Salvar</BaseButton>
          </div>
        </div>
      </form>
    </BaseModal>

    <BaseModal :open="importOpen" title="Importar fornecedores" subtitle="Use esta importação para fornecedores novos." @close="importOpen = false">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-border p-8 text-center opacity-60">
          <FileUp :size="28" class="text-txt-muted" />
          <p class="text-sm font-bold text-txt-primary">Selecionar planilha</p>
          <p class="text-xs text-txt-muted">Arquivos aceitos: XLSX ou XLS.</p>
          <StatusBadge label="Em breve" tone="warning" />
        </div>
        <div class="rounded-2xl border border-border p-5">
          <div class="flex items-center gap-2 text-amber-700">
            <AlertTriangle :size="16" />
            <span class="text-sm font-bold">Antes de importar</span>
          </div>
          <p class="mt-2 text-xs text-txt-secondary">
            Essa importação em massa ainda não foi implementada — por enquanto, cadastre pelo "Novo Fornecedor".
          </p>
          <BaseButton variant="ghost" :block="false" disabled class="mt-3">Baixar modelo XLSX</BaseButton>
        </div>
      </div>

      <div class="mt-5 flex justify-end gap-3 border-t border-border pt-4">
        <BaseButton type="button" variant="ghost" :block="false" @click="importOpen = false">Fechar</BaseButton>
        <BaseButton type="button" disabled :block="false">Importar</BaseButton>
      </div>
    </BaseModal>
  </div>
</template>
