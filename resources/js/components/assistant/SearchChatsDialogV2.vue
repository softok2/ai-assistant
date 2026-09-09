<script setup lang="ts">
import type { Chat, ChatHistory } from '@/types'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { Loader2, MessageSquare, Search, SquarePen } from 'lucide-vue-next'
import { computed, onUnmounted, ref, watch } from 'vue'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'

interface SearchResult {
  id: string
  title: string
  updated_at: string | null
}

const props = defineProps<{
  open: boolean
  chatHistory?: ChatHistory | null
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
}>()

const query = ref('')
const results = ref<SearchResult[]>([])
const searching = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
/** Descarta respuestas de búsquedas que ya quedaron atrás. */
let lastRequest = 0

/** Sin texto se muestran los chats ya cargados en la barra lateral. */
const recent = computed<SearchResult[]>(() => ((props.chatHistory?.data ?? []) as unknown as Chat[])
  .map(chat => ({ id: chat.id, title: chat.title, updated_at: chat.updated_at ?? null })))

const visible = computed<SearchResult[]>(() => (query.value.trim() ? results.value : recent.value))

async function search(): Promise<void> {
  const term = query.value.trim()
  const request = ++lastRequest

  if (!term) {
    results.value = []
    searching.value = false
    return
  }

  searching.value = true

  try {
    const { data } = await axios.get<SearchResult[]>(route('chats.search'), { params: { q: term } })

    if (request === lastRequest)
      results.value = data
  }
  catch {
    if (request === lastRequest)
      results.value = []
  }
  finally {
    if (request === lastRequest)
      searching.value = false
  }
}

watch(query, () => {
  clearTimeout(timer)
  timer = setTimeout(search, 250)
})

watch(() => props.open, (open) => {
  if (open) {
    lastRequest++
    query.value = ''
    results.value = []
  }
})

onUnmounted(() => clearTimeout(timer))

function go(chat: SearchResult): void {
  emit('update:open', false)
  router.visit(route('chats.show', { chat: chat.id }))
}

function newChat(): void {
  emit('update:open', false)
  router.visit(route('chats.index'))
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-lg">
      <DialogTitle class="sr-only">Buscar chats</DialogTitle>

      <div class="flex items-center gap-2 border-b border-border px-4">
        <Search class="size-4 shrink-0 text-muted-foreground" :stroke-width="1.5" />
        <input
          v-model="query"
          placeholder="Buscar en todos tus chats…"
          aria-label="Buscar chats"
          class="h-12 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
          autofocus
        >
        <Loader2 v-if="searching" class="size-4 shrink-0 animate-spin text-muted-foreground" :stroke-width="1.5" />
      </div>

      <div class="max-h-80 overflow-y-auto p-2">
        <button
          type="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted"
          @click="newChat"
        >
          <SquarePen class="size-4 text-muted-foreground" :stroke-width="1.5" />
          Nuevo chat
        </button>

        <p v-if="visible.length" class="px-3 pb-1 pt-3 text-xs text-muted-foreground">
          {{ query.trim() ? 'Resultados' : 'Recientes' }}
        </p>
        <button
          v-for="chat in visible"
          :key="chat.id"
          type="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted"
          @click="go(chat)"
        >
          <MessageSquare class="size-4 shrink-0 text-muted-foreground" :stroke-width="1.5" />
          <span class="truncate">{{ chat.title }}</span>
        </button>

        <p v-if="!visible.length && !searching" class="px-3 py-6 text-center text-sm text-muted-foreground">
          Sin resultados.
        </p>
      </div>
    </DialogContent>
  </Dialog>
</template>
