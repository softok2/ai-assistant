<script setup lang="ts">
import type { SourceFile, SourcesHealth } from '@/components/sources/types'
import type { ChatHistory } from '@/types'
import { Head, router, usePage } from '@inertiajs/vue3'
import { LoaderCircle, RefreshCw, ScanSearch, Upload } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AssistantLayout from '@/components/assistant/AssistantLayout.vue'
import ExpiredSourcesSection from '@/components/sources/ExpiredSourcesSection.vue'
import ReconcileDialog from '@/components/sources/ReconcileDialog.vue'
import SourcesHealthStrip from '@/components/sources/SourcesHealthStrip.vue'
import SourcesTable from '@/components/sources/SourcesTable.vue'
import UploadSourceDialog from '@/components/sources/UploadSourceDialog.vue'
import { Button } from '@/components/ui/button'

withDefaults(defineProps<{
  files: SourceFile[]
  expiredFiles?: SourceFile[]
  groups?: string[]
  syncRunning?: boolean
  health: SourcesHealth
  chatHistory?: ChatHistory | null
}>(), {
  expiredFiles: () => [],
  groups: () => [],
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

const busyFileId = ref<number | null>(null)
const uploading = ref(false)
const reconciling = ref(false)

function startSync(): void {
  router.post(route('sources.sync'), {}, { preserveScroll: true })
}

function reindex(file: SourceFile): void {
  busyFileId.value = file.id
  router.post(route('sources.files.reindex', { file: file.id }), {}, {
    preserveScroll: true,
    onFinish: () => (busyFileId.value = null),
  })
}

function remove(file: SourceFile): void {
  busyFileId.value = file.id
  router.delete(route('sources.files.destroy', { file: file.id }), {
    preserveScroll: true,
    onFinish: () => (busyFileId.value = null),
  })
}

function purgeExpired(): void {
  router.delete(route('sources.expired.purge'), { preserveScroll: true })
}
</script>

<template>
  <Head title="Fuentes del asistente" />

  <AssistantLayout :chat-history="chatHistory" title="Fuentes del asistente">
    <div class="flex-1 overflow-y-auto">
      <div class="mx-auto flex w-full max-w-5xl flex-col gap-7 px-4 py-8 md:px-10">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-[22px] font-semibold tracking-tight">Fuentes del asistente</h1>
            <p class="mt-1 text-sm text-muted-foreground">
              Reportes de Pentaho y documentos del club que el asistente consulta al responder.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" @click="reconciling = true">
              <ScanSearch class="size-4" :stroke-width="1.5" />
              Reconciliar con OpenAI
            </Button>
            <Button variant="outline" size="sm" :disabled="syncRunning" @click="startSync">
              <LoaderCircle v-if="syncRunning" class="size-4 animate-spin" :stroke-width="1.5" />
              <RefreshCw v-else class="size-4" :stroke-width="1.5" />
              {{ syncRunning ? 'Sincronizando…' : 'Sincronizar ahora' }}
            </Button>
            <Button size="sm" @click="uploading = true">
              <Upload class="size-4" :stroke-width="1.5" />
              Subir documento
            </Button>
          </div>
        </header>

        <SourcesHealthStrip :health="health" :sync-running="syncRunning" />

        <SourcesTable
          :files="files"
          :busy-file-id="busyFileId"
          @reindex="reindex"
          @remove="remove"
        />

        <ExpiredSourcesSection
          v-if="expiredFiles.length"
          :files="expiredFiles"
          :busy-file-id="busyFileId"
          @purge="purgeExpired"
          @reindex="reindex"
          @remove="remove"
        />
      </div>
    </div>

    <UploadSourceDialog v-model:open="uploading" :groups="groups" />
    <ReconcileDialog v-model:open="reconciling" />
  </AssistantLayout>
</template>
