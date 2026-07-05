<script setup lang="ts">
import type { Chat, ChatHistory } from '@/types'
import { router } from '@inertiajs/vue3'
import { MessageSquare, Search, SquarePen } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'

const props = defineProps<{
  open: boolean
  chatHistory?: ChatHistory | null
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
}>()

const query = ref('')

watch(() => props.open, (open) => {
  if (open)
    query.value = ''
})

const results = computed<Chat[]>(() => {
  const chats = props.chatHistory?.data ?? []
  const q = query.value.trim().toLowerCase()
  return q ? chats.filter(chat => chat.title.toLowerCase().includes(q)) : chats
})

function go(chat: Chat): void {
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
        <Search class="size-4 shrink-0 text-muted-foreground" />
        <input
          v-model="query"
          placeholder="Buscar chats..."
          class="h-12 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
          autofocus
        >
      </div>

      <div class="max-h-80 overflow-y-auto p-2">
        <button
          type="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted"
          @click="newChat"
        >
          <SquarePen class="size-4 text-muted-foreground" />
          Nuevo chat
        </button>

        <p v-if="results.length" class="px-3 pb-1 pt-3 text-xs text-muted-foreground">Chats</p>
        <button
          v-for="chat in results"
          :key="chat.id"
          type="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted"
          @click="go(chat)"
        >
          <MessageSquare class="size-4 shrink-0 text-muted-foreground" />
          <span class="truncate">{{ chat.title }}</span>
        </button>

        <p v-if="!results.length" class="px-3 py-6 text-center text-sm text-muted-foreground">
          Sin resultados.
        </p>
      </div>
    </DialogContent>
  </Dialog>
</template>
