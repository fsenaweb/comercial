<script setup lang="ts">
import { AlertTriangle, Check, CloudOff, Copy, Download, ExternalLink, HardDrive, PlayCircle, RefreshCw, RotateCcw, Send, Unlink, Upload } from 'lucide-vue-next'

interface LocalBackup {
  name: string
  path: string
  size: number
  created_at: string
}

interface RemoteBackupFile {
  id: string
  name: string
  size?: string
  createdTime?: string
}

interface GoogleDriveStatus {
  connected: boolean
  account_email: string | null
  connected_at: string | null
  files: RemoteBackupFile[]
  error: string | null
}

const api = useApi()
const { parse } = useApiError()
const { confirmDialog } = useConfirmDialog()
const config = useRuntimeConfig()

const localBackups = ref<LocalBackup[]>([])
const googleDrive = ref<GoogleDriveStatus>({ connected: false, account_email: null, connected_at: null, files: [], error: null })
const loading = ref(true)
const errorMessage = ref('')
const uploadingNow = ref(false)
const runningBackupNow = ref(false)

const connecting = ref(false)
const deviceCode = ref<{ user_code: string, verification_url: string } | null>(null)
let pollTimer: ReturnType<typeof setInterval> | null = null

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatDate(value: string | null): string {
  if (!value) return '-'
  return new Date(value).toLocaleString('pt-BR')
}

const codeCopied = ref(false)

async function copyDeviceCode() {
  if (!deviceCode.value) return

  await navigator.clipboard.writeText(deviceCode.value.user_code)
  codeCopied.value = true
  setTimeout(() => { codeCopied.value = false }, 2000)
}

const hasBackupToday = computed(() => {
  if (!localBackups.value.length) return false
  const today = new Date().toDateString()
  return new Date(localBackups.value[0]!.created_at).toDateString() === today
})

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const res = await api<{ data: { local: LocalBackup[], google_drive: GoogleDriveStatus } }>('/backups')
    localBackups.value = res.data.local
    googleDrive.value = res.data.google_drive
  } catch (error) {
    errorMessage.value = parse(error).message
  } finally {
    loading.value = false
  }
}

async function runBackupNow() {
  runningBackupNow.value = true
  errorMessage.value = ''

  try {
    await api('/backups/run', { method: 'POST' })
    await load()
  } catch (error) {
    errorMessage.value = parse(error).message
  } finally {
    runningBackupNow.value = false
  }
}

function downloadBackup(backup: LocalBackup) {
  const apiBase = (config.public.apiBase as string).replace(/\/$/, '')
  window.open(`${apiBase}/backups/${backup.path}/download`, '_blank')
}

async function connectGoogleDrive() {
  connecting.value = true
  errorMessage.value = ''

  try {
    const res = await api<{ data: { user_code: string, verification_url: string, interval: number } }>('/store-settings/google-drive/connect')
    deviceCode.value = res.data
    window.open(res.data.verification_url, '_blank')

    pollTimer = setInterval(async () => {
      try {
        const status = await api<{ data: { status: string, account_email?: string } }>('/store-settings/google-drive/status')

        if (status.data.status === 'connected') {
          stopPolling()
          deviceCode.value = null
          connecting.value = false
          await load()
        } else if (status.data.status === 'denied' || status.data.status === 'expired') {
          stopPolling()
          deviceCode.value = null
          connecting.value = false
          errorMessage.value = status.data.status === 'denied'
            ? 'Conexão negada pelo usuário no Google.'
            : 'O código expirou antes da autorização. Tente novamente.'
        }
      } catch (error) {
        // Falha transitória de rede não deve travar o polling para sempre;
        // uma falha real e persistente precisa aparecer, não ficar silenciosa.
        stopPolling()
        deviceCode.value = null
        connecting.value = false
        errorMessage.value = parse(error).message
      }
    }, (res.data.interval || 5) * 1000)
  } catch (error) {
    connecting.value = false
    errorMessage.value = parse(error).message
  }
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

async function disconnectGoogleDrive() {
  const confirmed = await confirmDialog({
    title: 'Desconectar Google Drive',
    message: 'Os backups já enviados continuam no Drive, mas novos backups deixam de ser enviados automaticamente. Deseja desconectar?',
    confirmLabel: 'Desconectar',
    variant: 'danger',
  })

  if (!confirmed) return

  errorMessage.value = ''

  try {
    await api('/store-settings/google-drive', { method: 'DELETE' })
    await load()
  } catch (error) {
    errorMessage.value = parse(error).message
  }
}

async function uploadLatestNow() {
  uploadingNow.value = true
  errorMessage.value = ''

  try {
    await api('/backups/upload-latest', { method: 'POST' })
    await load()
  } catch (error) {
    errorMessage.value = parse(error).message
  } finally {
    uploadingNow.value = false
  }
}

const restoreModalOpen = ref(false)
const restoreSource = ref<{ type: 'local', backup: LocalBackup } | { type: 'upload', file: File } | null>(null)
const restoreConfirmationText = ref('')
const restoreExpectedCode = ref('')
const loadingCode = ref(false)
const restoring = ref(false)
const restoreError = ref('')
const restoreSucceeded = ref(false)
const uploadFileInput = ref<HTMLInputElement | null>(null)

async function fetchRestoreConfirmationCode() {
  loadingCode.value = true
  restoreExpectedCode.value = ''

  try {
    const res = await api<{ data: { code: string } }>('/backups/restore/confirmation-code')
    restoreExpectedCode.value = res.data.code
  } catch (error) {
    restoreError.value = parse(error).message
  } finally {
    loadingCode.value = false
  }
}

function openRestoreModalForLocal(backup: LocalBackup) {
  restoreSource.value = { type: 'local', backup }
  restoreConfirmationText.value = ''
  restoreError.value = ''
  restoreModalOpen.value = true
  fetchRestoreConfirmationCode()
}

function onUploadFileSelected(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  restoreSource.value = { type: 'upload', file }
  restoreConfirmationText.value = ''
  restoreError.value = ''
  restoreModalOpen.value = true
  fetchRestoreConfirmationCode()
}

function closeRestoreModal() {
  restoreModalOpen.value = false
  restoreSource.value = null
  restoreExpectedCode.value = ''
  restoreSucceeded.value = false
  if (uploadFileInput.value) uploadFileInput.value.value = ''
}

async function confirmRestore() {
  if (!restoreSource.value || !restoreExpectedCode.value || restoreConfirmationText.value !== restoreExpectedCode.value) return

  restoring.value = true
  restoreError.value = ''

  const body = new FormData()
  body.append('confirmation', restoreConfirmationText.value)

  if (restoreSource.value.type === 'local') {
    body.append('filename', restoreSource.value.backup.path)
  } else {
    body.append('file', restoreSource.value.file)
  }

  try {
    await api('/backups/restore', { method: 'POST', body })
    // O restore derruba a sessão de todo mundo, inclusive quem restaurou
    // (a tabela sessions é truncada) - continuar nesta tela só geraria
    // erros de "Unauthenticated" em qualquer chamada seguinte. Redireciona
    // direto pro login em vez de tentar recarregar a página normalmente.
    restoreSucceeded.value = true
    setTimeout(() => { window.location.href = '/login' }, 2500)
  } catch (error) {
    restoreError.value = parse(error).message
    // O código já foi consumido no servidor mesmo com a restauração
    // rejeitada (ex.: caixa aberto) - gera um novo pra não travar o usuário.
    restoreConfirmationText.value = ''
    await fetchRestoreConfirmationCode()
  } finally {
    restoring.value = false
  }
}

onMounted(load)
onUnmounted(stopPolling)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-txt-primary">Configurações do sistema</h1>
    <p class="mt-1 text-sm text-txt-secondary">Centralize ajustes operacionais, fiscais e administrativos.</p>

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[296px_1fr]">
      <SettingsNav />

      <div class="flex min-w-0 flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <div>
            <span class="text-[10.5px] font-bold tracking-wide text-txt-muted uppercase">Área ativa</span>
            <h2 class="mt-1.5 font-display text-xl font-bold text-txt-primary">Backup e restauração</h2>
            <p class="mt-0.5 text-sm text-txt-secondary">Cópia local diária gerada automaticamente às 10:00; baixe manualmente ou conecte o Google Drive para envio automático.</p>
          </div>
          <BaseButton :block="false" :loading="runningBackupNow" loading-text="Gerando backup..." @click="runBackupNow">
            <PlayCircle :size="15" />
            Gerar backup agora
          </BaseButton>
        </div>

        <p v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-600">{{ errorMessage }}</p>

        <div v-if="!loading" class="rounded-2xl border p-4" :class="hasBackupToday ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
          <p class="text-sm font-bold" :class="hasBackupToday ? 'text-emerald-700' : 'text-amber-700'">
            {{ hasBackupToday ? 'Backup de hoje disponível' : 'Nenhum backup encontrado hoje' }}
          </p>
          <p class="mt-0.5 text-xs" :class="hasBackupToday ? 'text-emerald-700/80' : 'text-amber-700/80'">
            {{ hasBackupToday ? 'Baixe o arquivo mais recente e guarde num pendrive, HD externo ou nuvem pessoal.' : 'O backup diário roda às 10:00 - use o botão "Gerar backup agora" se precisar de uma cópia antes disso.' }}
          </p>
        </div>

        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <div class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface-subtle text-txt-secondary">
              <HardDrive :size="18" />
            </span>
            <div>
              <h3 class="font-display text-base font-bold text-txt-primary">Backups locais</h3>
              <p class="text-xs text-txt-secondary">Gerados diariamente às 10:00, guardados no servidor da loja.</p>
            </div>
          </div>

          <p v-if="loading" class="mt-4 text-sm text-txt-secondary">Carregando...</p>
          <p v-else-if="!localBackups.length" class="mt-4 text-sm text-txt-secondary">Nenhum backup local encontrado ainda.</p>
          <div v-else class="mt-4 divide-y divide-border">
            <div v-for="backup in localBackups" :key="backup.path" class="flex items-center justify-between gap-3 py-2.5">
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-txt-primary">{{ backup.name }}</p>
                <p class="text-xs text-txt-muted">{{ formatDate(backup.created_at) }} · {{ formatSize(backup.size) }}</p>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <button
                  type="button"
                  class="cursor-pointer flex items-center gap-1.5 rounded-full border border-border px-3 py-1.5 text-xs font-semibold text-txt-secondary transition hover:border-border-strong hover:text-txt-primary"
                  @click="downloadBackup(backup)"
                >
                  <Download :size="14" /> Baixar
                </button>
                <button
                  type="button"
                  class="cursor-pointer flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100"
                  @click="openRestoreModalForLocal(backup)"
                >
                  <RotateCcw :size="14" /> Restaurar
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
          <div class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface-subtle text-txt-secondary">
              <CloudOff v-if="!googleDrive.connected" :size="18" />
              <HardDrive v-else :size="18" />
            </span>
            <div>
              <h3 class="font-display text-base font-bold text-txt-primary">Google Drive</h3>
              <p class="text-xs text-txt-secondary">Envio automático diário do backup mais recente, depois da cópia local.</p>
            </div>
          </div>

          <template v-if="!googleDrive.connected">
            <div v-if="deviceCode" class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4">
              <p class="text-sm font-bold text-sky-800">Autorize em outro aparelho</p>
              <p class="mt-1 text-xs text-sky-700">Acesse <strong>{{ deviceCode.verification_url }}</strong> em qualquer celular ou computador com internet e digite o código:</p>
              <div class="mt-2 flex items-center gap-2">
                <p class="flex-1 rounded-lg bg-white px-3 py-2 text-center font-mono text-lg font-bold tracking-widest text-sky-900">{{ deviceCode.user_code }}</p>
                <button
                  type="button"
                  class="cursor-pointer flex h-full shrink-0 items-center gap-1.5 rounded-lg border border-sky-200 bg-white px-3 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-100"
                  @click="copyDeviceCode"
                >
                  <Check v-if="codeCopied" :size="14" />
                  <Copy v-else :size="14" />
                  {{ codeCopied ? 'Copiado' : 'Copiar' }}
                </button>
              </div>
              <a :href="deviceCode.verification_url" target="_blank" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-sky-700 hover:underline">
                Abrir página de autorização <ExternalLink :size="12" />
              </a>
            </div>
            <BaseButton v-else class="mt-4" variant="ghost" :block="false" :loading="connecting" loading-text="Aguardando autorização..." @click="connectGoogleDrive">
              Conectar Google Drive
            </BaseButton>
          </template>

          <template v-else>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-txt-primary">{{ googleDrive.account_email }}</p>
                <p class="text-xs text-txt-muted">Conectado em {{ formatDate(googleDrive.connected_at) }}</p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="cursor-pointer flex items-center gap-1.5 rounded-full border border-border px-3 py-1.5 text-xs font-semibold text-txt-secondary transition hover:border-border-strong hover:text-txt-primary disabled:opacity-60"
                  :disabled="uploadingNow"
                  @click="uploadLatestNow"
                >
                  <Send :size="14" /> {{ uploadingNow ? 'Enviando...' : 'Enviar backup mais recente agora' }}
                </button>
                <button
                  type="button"
                  class="cursor-pointer flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100"
                  @click="disconnectGoogleDrive"
                >
                  <Unlink :size="14" /> Desconectar
                </button>
              </div>
            </div>

            <p v-if="googleDrive.error" class="mt-3 flex items-center gap-1.5 text-xs text-amber-700">
              <RefreshCw :size="12" /> {{ googleDrive.error }}
            </p>

            <div v-else-if="googleDrive.files.length" class="mt-4 divide-y divide-border">
              <div v-for="file in googleDrive.files" :key="file.id" class="flex items-center justify-between gap-3 py-2.5">
                <p class="truncate text-sm font-semibold text-txt-primary">{{ file.name }}</p>
                <p class="text-xs text-txt-muted">{{ formatDate(file.createdTime ?? null) }}</p>
              </div>
            </div>
            <p v-else class="mt-4 text-sm text-txt-secondary">Nenhum backup enviado ao Drive ainda.</p>
          </template>
        </div>

        <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-5 shadow-card">
          <div class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
              <AlertTriangle :size="18" />
            </span>
            <div>
              <h3 class="font-display text-base font-bold text-txt-primary">Restaurar de um arquivo enviado</h3>
              <p class="text-xs text-txt-secondary">Envie um backup baixado do Google Drive, de outro pendrive/HD, ou qualquer <code>.zip</code> gerado por este sistema.</p>
            </div>
          </div>

          <input ref="uploadFileInput" type="file" accept=".zip" class="hidden" @change="onUploadFileSelected">
          <button
            type="button"
            class="cursor-pointer mt-4 flex items-center gap-1.5 rounded-full border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100"
            @click="uploadFileInput?.click()"
          >
            <Upload :size="14" /> Escolher arquivo e restaurar
          </button>
        </div>
      </div>
    </div>

    <BaseModal :open="restoreModalOpen" title="Restaurar backup" eyebrow="Ação irreversível" @close="closeRestoreModal">
      <template v-if="restoreSucceeded">
        <div class="flex flex-col items-center gap-3 py-6 text-center">
          <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <Check :size="24" />
          </span>
          <p class="font-display text-lg font-bold text-txt-primary">Backup restaurado com sucesso</p>
          <p class="text-sm text-txt-secondary">Sua sessão (e a de qualquer outro terminal logado) foi encerrada como parte da restauração. Redirecionando para o login...</p>
        </div>
      </template>

      <template v-else>
        <div class="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-4">
          <AlertTriangle :size="18" class="mt-0.5 shrink-0 text-rose-600" />
          <div class="text-sm text-rose-800">
            <p class="font-bold">Isso vai substituir TODOS os dados atuais.</p>
            <p class="mt-1">
              Vendas, estoque, caixa e cadastros feitos depois de
              <strong>{{ restoreSource?.type === 'local' ? formatDate(restoreSource.backup.created_at) : 'quando o arquivo enviado foi gerado' }}</strong>
              serão perdidos para sempre. Todo mundo com o sistema aberto (inclusive você, neste terminal) será desconectado e precisará entrar de novo.
            </p>
          </div>
        </div>

        <p class="mt-4 text-sm text-txt-secondary">
          Arquivo: <strong class="text-txt-primary">{{ restoreSource?.type === 'local' ? restoreSource.backup.name : restoreSource?.file.name }}</strong>
        </p>

        <label class="mt-4 block text-xs font-bold tracking-wide text-txt-muted uppercase">Digite o código abaixo para confirmar</label>
        <p v-if="loadingCode" class="mt-1.5 text-sm text-txt-secondary">Gerando código...</p>
        <p v-else class="mt-1.5 rounded-lg bg-rose-100 px-3 py-2 text-center font-mono text-lg font-bold tracking-widest text-rose-800">{{ restoreExpectedCode }}</p>

        <input
          v-model="restoreConfirmationText"
          type="text"
          class="mt-2 w-full rounded-xl border border-border px-3 py-2.5 text-center font-mono text-sm uppercase focus:border-rose-400 focus:ring-1 focus:ring-rose-400 focus:outline-none"
          placeholder="Digite o código"
          autocomplete="off"
        >

        <p v-if="restoreError" class="mt-3 text-sm text-rose-600">{{ restoreError }}</p>

        <div class="mt-5 flex justify-end gap-2.5">
          <button type="button" class="cursor-pointer rounded-full border border-border px-4 py-2 text-sm font-semibold text-txt-secondary hover:border-border-strong" @click="closeRestoreModal">
            Cancelar
          </button>
          <BaseButton
            :block="false"
            variant="danger"
            :disabled="!restoreExpectedCode || restoreConfirmationText !== restoreExpectedCode"
            :loading="restoring"
            loading-text="Restaurando..."
            @click="confirmRestore"
          >
            Restaurar agora
          </BaseButton>
        </div>
      </template>
    </BaseModal>
  </div>
</template>
