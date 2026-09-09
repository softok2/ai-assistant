<script setup lang="ts">
import axios from 'axios'
import { Check, Copy, Download, Ellipsis, Loader2, RefreshCw, Share, ThumbsDown, ThumbsUp, Volume2, VolumeX } from 'lucide-vue-next'
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { useReadAloud } from '@/composables/useReadAloud'

const props = defineProps<{
  messageId?: string
  isUpvoted?: boolean | null
  canWrite?: boolean
  canRegenerate?: boolean
  visible?: boolean
}>()

const emit = defineEmits<{
  (e: 'copy'): void
  (e: 'vote', isUpvoted: boolean): void
  (e: 'share'): void
  (e: 'regenerate'): void
}>()

const copied = ref(false)
const shared = ref(false)
const exporting = ref(false)

const { playing, loading: speechLoading, toggle: toggleSpeech } = useReadAloud()

function onCopy(): void {
  emit('copy')
  copied.value = true
  setTimeout(() => (copied.value = false), 1500)
}

function onShare(): void {
  emit('share')
  shared.value = true
  setTimeout(() => (shared.value = false), 1500)
}

/**
 * El PDF se pide por POST, así que hay que descargarlo desde el blob de la
 * respuesta en vez de navegar a la ruta.
 */
async function exportPdf(): Promise<void> {
  if (!props.messageId || exporting.value)
    return

  exporting.value = true

  try {
    const response = await axios.post(
      route('chat.messages.pdf', { message: props.messageId }),
      {},
      { responseType: 'blob' },
    )

    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filenameFrom(response.headers['content-disposition'])
    link.click()

    // Revocar en el mismo turno cancela la descarga en algunos navegadores.
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }
  catch {
    toast.error('No se pudo generar el PDF.')
  }
  finally {
    exporting.value = false
  }
}

function filenameFrom(disposition: unknown): string {
  const match = typeof disposition === 'string' ? disposition.match(/filename="?([^"]+)"?/) : null

  return match ? match[1] : 'respuesta.pdf'
}
</script>

<template>
  <TooltipProvider :delay-duration="300">
    <div
      class="mt-2 flex items-center gap-0.5 transition-opacity focus-within:opacity-100"
      :class="visible ? 'opacity-100' : 'opacity-0 group-hover/msg:opacity-100'"
    >
      <Tooltip>
        <TooltipTrigger as-child>
          <button type="button" class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Copiar" @click="onCopy">
            <Check v-if="copied" class="size-4 text-green-600" />
            <Copy v-else class="size-4" />
          </button>
        </TooltipTrigger>
        <TooltipContent>{{ copied ? 'Copiado' : 'Copiar' }}</TooltipContent>
      </Tooltip>

      <template v-if="messageId && canWrite">
        <Tooltip>
          <TooltipTrigger as-child>
            <button
              type="button"
              class="rounded-md p-1.5 transition-colors hover:bg-muted hover:text-foreground"
              :class="isUpvoted === true ? 'text-foreground' : 'text-muted-foreground'"
              aria-label="Buena respuesta"
              @click="emit('vote', true)"
            >
              <ThumbsUp class="size-4" />
            </button>
          </TooltipTrigger>
          <TooltipContent>Buena respuesta</TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger as-child>
            <button
              type="button"
              class="rounded-md p-1.5 transition-colors hover:bg-muted hover:text-foreground"
              :class="isUpvoted === false ? 'text-foreground' : 'text-muted-foreground'"
              aria-label="Mala respuesta"
              @click="emit('vote', false)"
            >
              <ThumbsDown class="size-4" />
            </button>
          </TooltipTrigger>
          <TooltipContent>Mala respuesta</TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger as-child>
            <button type="button" class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Compartir" @click="onShare">
              <Check v-if="shared" class="size-4 text-green-600" />
              <Share v-else class="size-4" />
            </button>
          </TooltipTrigger>
          <TooltipContent>{{ shared ? 'Enlace copiado' : 'Compartir' }}</TooltipContent>
        </Tooltip>
      </template>

      <Tooltip v-if="canRegenerate">
        <TooltipTrigger as-child>
          <button type="button" class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Regenerar" @click="emit('regenerate')">
            <RefreshCw class="size-4" />
          </button>
        </TooltipTrigger>
        <TooltipContent>Regenerar</TooltipContent>
      </Tooltip>

      <DropdownMenu v-if="messageId">
        <DropdownMenuTrigger as-child>
          <button
            type="button"
            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            aria-label="Más acciones"
          >
            <Ellipsis class="size-4" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
          <DropdownMenuItem @click="toggleSpeech(messageId!)">
            <Loader2 v-if="speechLoading" class="size-4 animate-spin" />
            <VolumeX v-else-if="playing" class="size-4" />
            <Volume2 v-else class="size-4" />
            {{ playing || speechLoading ? 'Detener lectura' : 'Leer en voz alta' }}
          </DropdownMenuItem>
          <DropdownMenuItem :disabled="exporting" @click="exportPdf">
            <Loader2 v-if="exporting" class="size-4 animate-spin" />
            <Download v-else class="size-4" />
            {{ exporting ? 'Generando PDF…' : 'Exportar PDF' }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </TooltipProvider>
</template>
