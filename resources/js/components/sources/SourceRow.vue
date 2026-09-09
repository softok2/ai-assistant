<script setup lang="ts">
import type { SourceFile } from './types'
import {
  Check,
  Ellipsis,
  FileText,
  LoaderCircle,
  RefreshCw,
  Trash2,
  TriangleAlert,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'
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
import { formatRelativeDay } from '@/lib/dates'
import { formatBytes, formatDate, originLabel, statusLabel } from './format'

const props = withDefaults(defineProps<{
  file: SourceFile
  /** En la sección de caducados se muestra la fecha en que se reemplazó. */
  showExpiry?: boolean
  /** Mientras la fila tiene una acción en vuelo no admite otra. */
  busy?: boolean
}>(), { showExpiry: false, busy: false })

const emit = defineEmits<{
  reindex: [file: SourceFile]
  remove: [file: SourceFile]
}>()

const confirming = ref(false)

const failed = computed(() => props.file.status === 'failed')
const indexed = computed(() => props.file.status === 'completed')
const processing = computed(() => props.file.status === 'in_progress')

const statusClass = computed(() => {
  if (failed.value)
    return 'bg-destructive/10 text-destructive'

  return indexed.value ? 'bg-brand-glow text-brand-deep' : 'bg-muted text-muted-foreground'
})

const updatedLabel = computed(() => {
  if (props.showExpiry)
    return formatDate(props.file.expired_at)

  if (failed.value)
    return 'sin actualizar'

  return formatRelativeDay(props.file.synced_at) ?? 'sin actualizar'
})

function askToRemove(event: Event): void {
  event.preventDefault()
  confirming.value = true
}
</script>

<template>
  <li
    class="grid min-h-13 grid-cols-[minmax(0,1.6fr)_auto] items-center gap-4 border-t border-border px-4 py-2 first:border-t-0 md:grid-cols-[minmax(0,1.6fr)_5rem_7.5rem_8rem_5rem_4rem]"
  >
    <div class="flex min-w-0 items-center gap-3">
      <span class="flex size-8.5 shrink-0 items-center justify-center rounded-[9px] bg-muted text-muted-foreground">
        <FileText class="size-4" :stroke-width="1.5" />
      </span>
      <div class="min-w-0">
        <p class="text-sm font-medium">{{ props.file.group_label }}</p>
        <p class="truncate text-xs text-muted-foreground" :title="props.file.name">{{ props.file.name }}</p>
      </div>
    </div>

    <div class="hidden text-[13px] text-muted-foreground md:block">
      {{ originLabel[props.file.origin] ?? props.file.origin }}
    </div>

    <div class="hidden md:block">
      <span
        class="inline-flex h-5.5 items-center gap-1.5 rounded-full px-2 text-xs font-medium"
        :class="statusClass"
      >
        <TriangleAlert v-if="failed" class="size-3" :stroke-width="1.5" />
        <LoaderCircle v-else-if="processing" class="size-3 animate-spin" :stroke-width="1.5" />
        <Check v-else-if="indexed" class="size-3" :stroke-width="1.5" />
        {{ statusLabel[props.file.status] ?? props.file.status }}
      </span>
    </div>

    <div
      class="hidden text-[13px] tabular-nums md:block"
      :class="failed && !props.showExpiry ? 'text-destructive' : 'text-muted-foreground'"
    >
      {{ updatedLabel }}
    </div>

    <div class="hidden text-[13px] tabular-nums text-muted-foreground md:block">
      {{ formatBytes(props.file.bytes) }}
    </div>

    <div class="flex justify-end">
      <Button
        v-if="failed"
        variant="outline"
        size="sm"
        class="h-7 px-2.5 text-[13px]"
        :disabled="props.busy"
        @click="emit('reindex', props.file)"
      >
        <LoaderCircle v-if="props.busy" class="size-3.5 animate-spin" :stroke-width="1.5" />
        <RefreshCw v-else class="size-3.5" :stroke-width="1.5" />
        Reintentar
      </Button>

      <DropdownMenu v-else>
        <DropdownMenuTrigger as-child>
          <Button
            variant="ghost"
            size="icon"
            class="size-7 text-muted-foreground"
            :disabled="props.busy"
            :aria-label="`Acciones de ${props.file.name}`"
          >
            <LoaderCircle v-if="props.busy" class="size-4 animate-spin" :stroke-width="1.5" />
            <Ellipsis v-else class="size-4" :stroke-width="1.5" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem :disabled="props.busy" @select="emit('reindex', props.file)">
            <RefreshCw class="size-4" :stroke-width="1.5" />
            Reindexar
          </DropdownMenuItem>
          <DropdownMenuItem
            :disabled="props.busy"
            class="text-destructive focus:bg-destructive/10 focus:text-destructive [&_svg]:!text-destructive"
            @select="askToRemove"
          >
            <Trash2 class="size-4" :stroke-width="1.5" />
            Eliminar
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>

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
