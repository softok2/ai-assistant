<script setup lang="ts">
import type { LibraryFile } from './types'
import { Ellipsis, FileText, LoaderCircle, RefreshCw, Trash2, TriangleAlert } from 'lucide-vue-next'
import { ref } from 'vue'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { formatBytes, formatDate, statusLabel } from './format'

const props = withDefaults(defineProps<{
  file: LibraryFile
  canManage?: boolean
  showExpiry?: boolean
  /** Mientras la fila tiene una acción en vuelo no admite otra. */
  busy?: boolean
}>(), { canManage: false, showExpiry: false, busy: false })

const emit = defineEmits<{
  reindex: [file: LibraryFile]
  remove: [file: LibraryFile]
}>()

const confirming = ref(false)

const statusClass: Record<string, string> = {
  pending: 'bg-muted text-muted-foreground',
  in_progress: 'bg-muted text-foreground',
  completed: 'bg-muted text-foreground',
  failed: 'bg-destructive/10 text-destructive',
}

function askToRemove(event: Event): void {
  event.preventDefault()
  confirming.value = true
}
</script>

<template>
  <li class="flex items-center gap-3 px-4 py-3">
    <FileText class="size-4 shrink-0 text-muted-foreground" />
    <span class="flex-1 truncate text-sm" :title="props.file.name">{{ props.file.name }}</span>
    <span class="hidden text-xs text-muted-foreground sm:inline">{{ formatBytes(props.file.bytes) }}</span>
    <span class="hidden text-xs text-muted-foreground sm:inline">
      {{ props.showExpiry ? `Caducó ${formatDate(props.file.expired_at)}` : formatDate(props.file.synced_at) }}
    </span>
    <span
      class="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
      :class="statusClass[props.file.status] ?? 'bg-muted text-muted-foreground'"
    >
      <TriangleAlert v-if="props.file.status === 'failed'" class="size-3" />
      {{ statusLabel[props.file.status] ?? props.file.status }}
    </span>

    <DropdownMenu v-if="props.canManage">
      <DropdownMenuTrigger as-child>
        <Button
          variant="ghost"
          size="icon"
          class="size-8"
          :disabled="props.busy"
          :aria-label="`Acciones de ${props.file.name}`"
        >
          <LoaderCircle v-if="props.busy" class="size-4 animate-spin" />
          <Ellipsis v-else class="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem
          :disabled="props.busy"
          @select="emit('reindex', props.file)"
        >
          <RefreshCw class="size-4" />
          Reindexar
        </DropdownMenuItem>
        <DropdownMenuItem
          :disabled="props.busy"
          class="text-destructive focus:bg-destructive/10 focus:text-destructive [&_svg]:!text-destructive"
          @select="askToRemove"
        >
          <Trash2 class="size-4" />
          Eliminar
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>

    <AlertDialog v-model:open="confirming">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Eliminar documento</AlertDialogTitle>
          <AlertDialogDescription>
            Se borrará {{ props.file.name }} de OpenAI, del disco y de la base. El asistente dejará de consultarlo.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction
            class="bg-destructive text-white hover:bg-destructive/90"
            @click="emit('remove', props.file)"
          >
            Eliminar
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </li>
</template>
