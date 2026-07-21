<script setup lang="ts">
import { Pencil, Plus, Search, UserCheck } from 'lucide-vue-next'

interface User {
  id: number
  name: string
  email: string | null
  role: 'admin' | 'cashier' | 'seller'
  role_label: string
  commission_percent: string | null
  active: boolean
}

const roleOptions = [
  { value: 'admin', label: 'Administrador' },
  { value: 'cashier', label: 'Caixa' },
  { value: 'seller', label: 'Vendedor' },
]

const api = useResourceApi<User>('users')
const { parse, firstFieldError } = useApiError()
const auth = useAuthStore()

const users = ref<User[]>([])
const loading = ref(true)
const search = ref('')

async function load() {
  loading.value = true
  users.value = await api.list()
  loading.value = false
}

const filteredUsers = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return users.value
  return users.value.filter((u) => u.name.toLowerCase().includes(query) || (u.email ?? '').toLowerCase().includes(query))
})

// ---- Modal "Novo Usuário" / "Editar Usuário" ----

const modalOpen = ref(false)
const modalSaving = ref(false)
const modalError = ref<unknown>(null)
const editingId = ref<number | null>(null)

function emptyForm() {
  return {
    name: '',
    email: '',
    password: '',
    role: 'seller' as string,
    commission_percent: null as string | null,
    active: true,
  }
}

const form = reactive(emptyForm())

const isEditingSelf = computed(() => editingId.value !== null && editingId.value === auth.user?.id)

function openCreateModal() {
  editingId.value = null
  Object.assign(form, emptyForm())
  modalError.value = null
  modalOpen.value = true
}

function openEditModal(user: User) {
  editingId.value = user.id
  Object.assign(form, { ...user, email: user.email ?? '', password: '' })
  modalError.value = null
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
}

async function handleSubmit() {
  modalSaving.value = true
  modalError.value = null

  try {
    const payload: Record<string, unknown> = { ...form, email: form.email.trim() || null }
    if (editingId.value && !form.password) delete payload.password

    if (editingId.value) {
      await api.update(editingId.value, payload)
    } else {
      await api.create(payload)
    }
    closeModal()
    await load()
  } catch (err) {
    modalError.value = err
  } finally {
    modalSaving.value = false
  }
}

await load()
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="font-display text-[30px] font-extrabold text-brand">Usuários e Permissões</h1>
      <p class="text-sm text-txt-secondary">Contas de acesso ao sistema - administradores, caixas e vendedores.</p>
    </div>

    <StatCard label="Usuários" :value="users.length" subtext="contas cadastradas" :icon="UserCheck" tone="violet" class="max-w-xs" />

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-surface-raised p-4 shadow-card">
      <BaseButton :block="false" @click="openCreateModal">
        <Plus :size="15" />
        Novo Usuário
      </BaseButton>
      <div class="flex-1" />
      <label class="flex w-full max-w-xs items-center gap-2 rounded-full border border-border bg-surface px-3.5 py-2 text-txt-muted">
        <input v-model="search" type="text" placeholder="Nome ou e-mail" class="w-full bg-transparent text-sm text-txt-primary placeholder:text-txt-muted focus:outline-none">
        <Search :size="15" />
      </label>
    </div>

    <div class="rounded-2xl border border-border bg-surface-raised shadow-card">
      <div class="grid grid-cols-[1.4fr_1.6fr_1fr_0.8fr_60px] items-center gap-2 border-b border-border px-5 py-3.5 text-[11px] font-bold tracking-wide text-txt-secondary uppercase">
        <span>Nome</span>
        <span>E-mail</span>
        <span>Papel</span>
        <span>Status</span>
        <span class="text-right">Ações</span>
      </div>

      <div v-if="loading" class="px-5 py-11 text-center text-sm text-txt-muted">Carregando...</div>
      <div v-else-if="filteredUsers.length === 0" class="px-5 py-11 text-center text-sm text-txt-muted">
        Nenhum usuário cadastrado ainda.
      </div>
      <div
        v-for="user in filteredUsers"
        v-else
        :key="user.id"
        class="grid grid-cols-[1.4fr_1.6fr_1fr_0.8fr_60px] items-center gap-2 border-b border-border px-5 py-3 last:border-0 hover:bg-surface-subtle"
      >
        <span class="text-sm font-medium text-txt-primary">{{ user.name }}</span>
        <span class="truncate text-sm text-txt-secondary">{{ user.email ?? '-' }}</span>
        <span><StatusBadge :label="user.role_label" tone="info" /></span>
        <span><StatusBadge :label="user.active ? 'Ativo' : 'Inativo'" :tone="user.active ? 'success' : 'danger'" /></span>
        <div class="flex justify-end">
          <IconButton :icon="Pencil" label="Editar" @click="openEditModal(user)" />
        </div>
      </div>

      <div class="flex items-center justify-end border-t border-border px-5 py-3.5">
        <span class="text-xs text-txt-secondary">
          Exibindo <strong class="text-txt-primary">{{ filteredUsers.length }}</strong> de
          <strong class="text-txt-primary">{{ users.length }}</strong>
        </span>
      </div>
    </div>

    <BaseModal
      :open="modalOpen"
      :title="editingId ? 'Editar usuário' : 'Novo usuário'"
      subtitle="Defina o acesso e o papel do usuário no sistema."
      @close="closeModal"
    >
      <form class="space-y-4" @submit.prevent="handleSubmit">
        <BaseInput v-model="form.name" label="Nome" :error="firstFieldError(modalError, 'name')" />
        <BaseInput v-model="form.email" type="email" label="E-mail (opcional - só quem faz login precisa de um)" :error="firstFieldError(modalError, 'email')" />
        <BaseInput
          v-model="form.password"
          type="password"
          :label="editingId ? 'Nova senha (deixe em branco para manter a atual)' : 'Senha'"
          :error="firstFieldError(modalError, 'password')"
        />
        <BaseSelect v-model="form.role" label="Papel" :options="roleOptions" :error="firstFieldError(modalError, 'role')" />
        <BaseInput
          v-if="form.role === 'seller'"
          v-model="form.commission_percent"
          type="number"
          label="Comissão (%)"
          :error="firstFieldError(modalError, 'commission_percent')"
        />

        <div class="flex items-center justify-between gap-3 border-t border-border pt-4">
          <BaseSwitch v-model="form.active" label="Ativo" :disabled="isEditingSelf" />
        </div>
        <p v-if="isEditingSelf" class="text-xs text-txt-muted">Você não pode desativar a própria conta.</p>

        <p v-if="modalError" class="text-sm text-rose-600">{{ parse(modalError).message }}</p>

        <div class="flex justify-end gap-3 border-t border-border pt-4">
          <BaseButton type="button" variant="ghost" :block="false" @click="closeModal">Cancelar</BaseButton>
          <BaseButton type="submit" :loading="modalSaving" :block="false">Salvar</BaseButton>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
