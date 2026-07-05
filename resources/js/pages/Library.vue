<script setup lang="ts">
import type { ChatHistory } from '@/types'
import { Head } from '@inertiajs/vue3'
import { BookMarked, FileText } from 'lucide-vue-next'
import { computed } from 'vue'
import AssistantLayout from '@/components/assistant/AssistantLayout.vue'

interface LibraryFile {
  id: number
  name: string
  group: string
  status: string
  bytes: number | null
  synced_at: string | null
}

const props = defineProps<{
  files: LibraryFile[]
  chatHistory?: ChatHistory | null
}>()

const groups = computed(() => {
  const map = new Map<string, LibraryFile[]>()
  for (const file of props.files) {
    const list = map.get(file.group) ?? []
    list.push(file)
    map.set(file.group, list)
  }
  return [...map.entries()].map(([name, files]) => ({ name, files }))
})

function formatBytes(bytes: number | null): string {
  if (!bytes)
    return '—'
  const units = ['B', 'KB', 'MB']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }
  return `${value.toFixed(unit ? 1 : 0)} ${units[unit]}`
}

function formatDate(date: string | null): string {
  return date ? new Date(date).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

const statusLabel: Record<string, string> = {
  pending: 'Pendiente',
  in_progress: 'Procesando',
  completed: 'Indexado',
  failed: 'Falló',
}
</script>

<template>
  <Head title="Biblioteca" />

  <AssistantLayout :chat-history="chatHistory">
    <div class="flex-1 overflow-y-auto">
      <div class="mx-auto w-full max-w-3xl px-4 py-8 md:px-6">
        <div class="mb-6 flex items-center gap-3">
          <BookMarked class="size-6 text-muted-foreground" />
          <div>
            <h1 class="text-xl font-semibold">Biblioteca</h1>
            <p class="text-sm text-muted-foreground">Documentos del club que el asistente conoce y consulta al responder.</p>
          </div>
        </div>

        <div v-for="group in groups" :key="group.name" class="mb-6">
          <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ group.name }}</p>
          <ul class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card">
            <li v-for="file in group.files" :key="file.id" class="flex items-center gap-3 px-4 py-3">
              <FileText class="size-4 shrink-0 text-muted-foreground" />
              <span class="flex-1 truncate text-sm">{{ file.name }}</span>
              <span class="text-xs text-muted-foreground">{{ formatBytes(file.bytes) }}</span>
              <span class="text-xs text-muted-foreground">{{ formatDate(file.synced_at) }}</span>
              <span
                class="rounded-full px-2 py-0.5 text-xs"
                :class="file.status === 'completed' ? 'bg-muted text-foreground' : 'bg-muted text-muted-foreground'"
              >{{ statusLabel[file.status] ?? file.status }}</span>
            </li>
          </ul>
        </div>

        <p v-if="!files.length" class="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">
          Aún no hay documentos indexados.
        </p>
      </div>
    </div>
  </AssistantLayout>
</template>
