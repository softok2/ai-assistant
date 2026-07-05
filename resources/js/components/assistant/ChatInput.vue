<script setup lang="ts">
import type { Model } from '@/types'
import type { ChatAttachment } from '@/composables/useChatUploads'
import { useStorage } from '@vueuse/core'
import { ArrowUp, ChevronDown, FileText, Globe, Loader2, Mic, Paperclip, Plus, Square, X } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Textarea } from '@/components/ui/textarea'
import { useChatUploads } from '@/composables/useChatUploads'
import { useVoiceDictation } from '@/composables/useVoiceDictation'

const props = defineProps<{
  models: Model[]
  busy?: boolean
}>()

const emit = defineEmits<{
  (e: 'submit', message: string, model: string | null, attachments: ChatAttachment[]): void
  (e: 'stop'): void
}>()

const input = ref('')
const fileInput = ref<HTMLInputElement>()

const selectedModelId = useStorage<string>('assistant-model', '')

const selectedModel = computed<Model | null>(
  () => props.models.find(model => model.id === selectedModelId.value) ?? null,
)

const { attachments, uploading, upload, remove, clear } = useChatUploads()

const { recording, transcribing, supported: micSupported, start: startDictation, stop: stopDictation } = useVoiceDictation((text) => {
  input.value = input.value ? `${input.value} ${text}` : text
})

function submit(): void {
  const message = input.value.trim()
  if (!message || props.busy || uploading.value)
    return

  const files = [...attachments.value]
  input.value = ''
  clear()
  emit('submit', message, selectedModel.value?.id ?? null, files)
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    submit()
  }
}

function onFilesSelected(event: Event): void {
  const files = (event.target as HTMLInputElement).files
  if (files?.length)
    upload(files)

  if (fileInput.value)
    fileInput.value.value = ''
}

defineExpose({
  setInput: (value: string) => (input.value = value),
})
</script>

<template>
  <div class="rounded-2xl border border-border bg-card shadow-sm">
    <!-- Attachment previews -->
    <div v-if="attachments.length || uploading" class="flex flex-wrap gap-2 px-4 pt-3">
      <div
        v-for="attachment in attachments"
        :key="attachment.path"
        class="group/chip relative flex items-center gap-2 rounded-lg border border-border bg-muted/40 p-1.5 pr-3"
      >
        <img
          v-if="attachment.mime.startsWith('image/')"
          :src="attachment.url"
          :alt="attachment.name"
          class="size-10 rounded-md object-cover"
        >
        <span v-else class="flex size-10 items-center justify-center rounded-md bg-muted">
          <FileText class="size-5 text-muted-foreground" />
        </span>
        <span class="max-w-36 truncate text-xs">{{ attachment.name }}</span>
        <button
          type="button"
          class="absolute -right-1.5 -top-1.5 rounded-full border border-border bg-background p-0.5 text-muted-foreground opacity-0 transition-opacity hover:text-foreground group-hover/chip:opacity-100"
          aria-label="Quitar adjunto"
          @click="remove(attachment.path)"
        >
          <X class="size-3" />
        </button>
      </div>
      <div v-if="uploading" class="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
        <Loader2 class="size-3.5 animate-spin" />
        Subiendo...
      </div>
    </div>

    <Textarea
      v-model="input"
      placeholder="Pregunta lo que quieras..."
      class="min-h-16 resize-none border-0 bg-transparent px-4 pt-4 shadow-none focus-visible:ring-0"
      rows="2"
      @keydown="onKeydown"
    />

    <div class="flex items-center justify-between gap-2 px-3 pb-3">
      <div class="flex items-center gap-1.5">
        <!-- "+" menu (ChatGPT-style) -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <button
              type="button"
              class="flex size-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              aria-label="Más opciones"
            >
              <Plus class="size-4.5" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" class="w-72">
            <DropdownMenuItem @click="fileInput?.click()">
              <Paperclip class="size-4" />
              <div>
                <p class="text-sm">Adjuntar fotos y archivos</p>
                <p class="text-xs text-muted-foreground">Imágenes, PDF, Excel, Word...</p>
              </div>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <!-- Model selector -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-full border border-border px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
            >
              <Globe class="size-3.5 text-muted-foreground" />
              {{ selectedModel?.name ?? 'Modelo por defecto' }}
              <ChevronDown class="size-3.5 text-muted-foreground" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" class="w-72">
            <DropdownMenuItem @click="selectedModelId = ''">
              <div>
                <p class="text-sm font-medium">Modelo por defecto</p>
                <p class="text-xs text-muted-foreground">Configurado por el club</p>
              </div>
            </DropdownMenuItem>
            <DropdownMenuItem v-for="model in models" :key="model.id" @click="selectedModelId = model.id">
              <div>
                <p class="text-sm font-medium">{{ model.name }}</p>
                <p class="text-xs text-muted-foreground">{{ model.description }}</p>
              </div>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <div class="flex items-center gap-1.5">
        <!-- Dictation -->
        <button
          v-if="micSupported"
          type="button"
          class="flex size-8 items-center justify-center rounded-full transition-colors"
          :class="recording
            ? 'animate-pulse bg-red-500/15 text-red-500'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
          :aria-label="recording ? 'Detener dictado' : 'Dictar por voz'"
          :disabled="transcribing"
          @click="recording ? stopDictation() : startDictation()"
        >
          <Loader2 v-if="transcribing" class="size-4 animate-spin" />
          <Square v-else-if="recording" class="size-3.5 fill-current" />
          <Mic v-else class="size-4" />
        </button>

        <Button
          v-if="busy"
          size="icon"
          variant="secondary"
          class="rounded-full"
          aria-label="Detener"
          @click="emit('stop')"
        >
          <Square class="size-4" />
        </Button>
        <Button
          v-else
          size="icon"
          class="rounded-full"
          :disabled="!input.trim() || uploading"
          aria-label="Enviar mensaje"
          @click="submit"
        >
          <ArrowUp class="size-4" />
        </Button>
      </div>
    </div>

    <input
      ref="fileInput"
      type="file"
      multiple
      accept="image/*,.pdf,.txt,.md,.csv,.xls,.xlsx,.doc,.docx"
      class="hidden"
      @change="onFilesSelected"
    >
  </div>
</template>
