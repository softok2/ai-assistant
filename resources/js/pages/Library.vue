<script setup lang="ts">
import type { LibraryFile } from '@/components/library/types'
import type { ChatHistory } from '@/types'
import { Head, router, usePage } from '@inertiajs/vue3'
import { BookMarked, LoaderCircle, RefreshCw, ScanSearch, Trash2, Upload } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AssistantLayout from '@/components/assistant/AssistantLayout.vue'
import LibraryFileRow from '@/components/library/LibraryFileRow.vue'
import ReconcileDialog from '@/components/library/ReconcileDialog.vue'
import UploadLibraryFileDialog from '@/components/library/UploadLibraryFileDialog.vue'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'

const props = withDefaults(defineProps<{
  files: LibraryFile[]
  expiredFiles?: LibraryFile[]
  groups?: string[]
  canManage?: boolean
  syncRunning?: boolean
  chatHistory?: ChatHistory | null
}>(), {
  expiredFiles: () => [],
  groups: () => [],
  canManage: false,
  syncRunning: false,
})

const page = usePage()
watch(() => page.props.flash, (flash: any) => {
  if (flash?.success)
    toast.success(flash.success)
  if (flash?.warning)
    toast.warning(flash.warning)
  if (flash?.error)
    toast.error(flash.error)
}, { immediate: true, deep: true })

const tab = ref<'active' | 'expired'>('active')
const busyFileId = ref<number | null>(null)
const uploading = ref(false)
const reconciling = ref(false)

const visibleFiles = computed(() => (tab.value === 'expired' ? props.expiredFiles : props.files))

const groupedFiles = computed(() => {
  const map = new Map<string, LibraryFile[]>()
  for (const file of visibleFiles.value) {
    const list = map.get(file.group) ?? []
    list.push(file)
    map.set(file.group, list)
  }
  return [...map.entries()].map(([name, files]) => ({ name, files }))
})

const emptyMessage = computed(() => (tab.value === 'expired'
  ? 'No hay documentos caducados.'
  : 'Aún no hay documentos indexados.'))

function startSync(): void {
  router.post(route('library.sync'), {}, { preserveScroll: true })
}

function reindex(file: LibraryFile): void {
  busyFileId.value = file.id
  router.post(route('library.files.reindex', { file: file.id }), {}, {
    preserveScroll: true,
    onFinish: () => (busyFileId.value = null),
  })
}

function remove(file: LibraryFile): void {
  busyFileId.value = file.id
  router.delete(route('library.files.destroy', { file: file.id }), {
    preserveScroll: true,
    onFinish: () => (busyFileId.value = null),
  })
}

function purgeExpired(): void {
  router.delete(route('library.expired.purge'), { preserveScroll: true })
}
</script>

<template>
  <Head title="Biblioteca" />

  <AssistantLayout :chat-history="chatHistory" title="Biblioteca">
    <div class="flex-1 overflow-y-auto">
      <div class="mx-auto w-full max-w-3xl px-4 py-8 md:px-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex items-center gap-3">
            <BookMarked class="size-6 text-muted-foreground" />
            <div>
              <h1 class="text-xl font-semibold">Biblioteca</h1>
              <p class="text-sm text-muted-foreground">Documentos del club que el asistente conoce y consulta al responder.</p>
            </div>
          </div>

          <div v-if="canManage" class="flex flex-wrap items-center gap-2">
            <Button size="sm" @click="uploading = true">
              <Upload class="size-4" />
              Subir documento
            </Button>
            <Button size="sm" variant="outline" :disabled="syncRunning" @click="startSync">
              <RefreshCw class="size-4" :class="syncRunning && 'animate-spin'" />
              {{ syncRunning ? 'Sincronizando…' : 'Sincronizar ahora' }}
            </Button>
            <Button size="sm" variant="outline" @click="reconciling = true">
              <ScanSearch class="size-4" />
              Reconciliar con OpenAI
            </Button>
          </div>
        </div>

        <p v-if="canManage && syncRunning" class="mb-4 flex items-center gap-2 text-xs text-muted-foreground">
          <LoaderCircle class="size-3 animate-spin" />
          Sincronización en curso…
        </p>

        <div v-if="canManage" class="mb-4 flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            :aria-pressed="tab === 'active'"
            :class="tab === 'active' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
            @click="tab = 'active'"
          >
            Activos ({{ files.length }})
          </Button>
          <Button
            variant="ghost"
            size="sm"
            :aria-pressed="tab === 'expired'"
            :class="tab === 'expired' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
            @click="tab = 'expired'"
          >
            Caducados ({{ expiredFiles.length }})
          </Button>

          <AlertDialog v-if="tab === 'expired' && expiredFiles.length">
            <AlertDialogTrigger as-child>
              <Button variant="ghost" size="sm" class="ml-auto text-destructive hover:text-destructive">
                <Trash2 class="size-4" />
                Purgar caducados
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Purgar documentos caducados</AlertDialogTitle>
                <AlertDialogDescription>
                  Se borrarán {{ expiredFiles.length }} documentos de OpenAI, del disco y de la base. La purga corre en segundo plano.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="purgeExpired">
                  Purgar
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </div>

        <div v-for="group in groupedFiles" :key="group.name" class="mb-6">
          <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ group.name }}</p>
          <ul class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card">
            <LibraryFileRow
              v-for="file in group.files"
              :key="file.id"
              :file="file"
              :can-manage="canManage"
              :show-expiry="tab === 'expired'"
              :busy="busyFileId === file.id"
              @reindex="reindex"
              @remove="remove"
            />
          </ul>
        </div>

        <p v-if="!visibleFiles.length" class="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">
          {{ emptyMessage }}
        </p>
      </div>
    </div>

    <UploadLibraryFileDialog v-if="canManage" v-model:open="uploading" :groups="groups" />
    <ReconcileDialog v-if="canManage" v-model:open="reconciling" />
  </AssistantLayout>
</template>
