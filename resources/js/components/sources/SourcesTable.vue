<script setup lang="ts">
import type { SourceFile } from './types'
import SourceRow from './SourceRow.vue'

const props = withDefaults(defineProps<{
  files: SourceFile[]
  /** En caducados la columna de fecha muestra cuándo se reemplazó. */
  showExpiry?: boolean
  busyFileId?: number | null
  emptyMessage?: string
}>(), {
  showExpiry: false,
  busyFileId: null,
  emptyMessage: 'Aún no hay documentos indexados.',
})

const emit = defineEmits<{
  reindex: [file: SourceFile]
  remove: [file: SourceFile]
}>()
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
    <div
      class="hidden h-10 items-center gap-4 bg-muted px-4 text-xs text-muted-foreground md:grid md:grid-cols-[minmax(0,1.6fr)_5rem_7.5rem_8rem_5rem_4rem]"
    >
      <div>Fuente</div>
      <div>Origen</div>
      <div>Estado</div>
      <div>{{ props.showExpiry ? 'Caducó' : 'Actualizado' }}</div>
      <div>Tamaño</div>
      <div class="sr-only">Acciones</div>
    </div>

    <ul v-if="props.files.length">
      <SourceRow
        v-for="file in props.files"
        :key="file.id"
        :file="file"
        :show-expiry="props.showExpiry"
        :busy="props.busyFileId === file.id"
        @reindex="emit('reindex', $event)"
        @remove="emit('remove', $event)"
      />
    </ul>

    <p v-else class="border-t border-border p-8 text-center text-sm text-muted-foreground">
      {{ props.emptyMessage }}
    </p>
  </div>
</template>
