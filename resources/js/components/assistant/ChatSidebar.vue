<script setup lang="ts">
import type { Chat, ChatHistory } from '@/types'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useStorage } from '@vueuse/core'
import { BookMarked, FileText, Link2, Lock, MoreHorizontal, PanelLeft, Search, SquarePen, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import SearchChatsDialog from './SearchChatsDialog.vue'

const props = defineProps<{
  chatHistory?: ChatHistory | null
  activeChatId?: string
}>()

const collapsed = useStorage('assistant-sidebar-collapsed', false)
const searchOpen = ref(false)

const isAdmin = computed(() => (usePage().props as any).auth?.is_admin ?? false)

const chats = computed<Chat[]>(() => props.chatHistory?.data ?? [])

interface ChatGroup {
  label: string
  chats: Chat[]
}

const groups = computed<ChatGroup[]>(() => {
  const now = new Date()
  const startOfDay = (daysAgo: number): Date => {
    const d = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    d.setDate(d.getDate() - daysAgo)
    return d
  }

  const buckets: ChatGroup[] = [
    { label: 'Hoy', chats: [] },
    { label: 'Ayer', chats: [] },
    { label: 'Últimos 7 días', chats: [] },
    { label: 'Anteriores', chats: [] },
  ]

  for (const chat of chats.value) {
    const updated = new Date(chat.updated_at ?? chat.created_at ?? now)
    if (updated >= startOfDay(0))
      buckets[0].chats.push(chat)
    else if (updated >= startOfDay(1))
      buckets[1].chats.push(chat)
    else if (updated >= startOfDay(7))
      buckets[2].chats.push(chat)
    else
      buckets[3].chats.push(chat)
  }

  return buckets.filter(group => group.chats.length > 0)
})

function deleteChat(chat: Chat): void {
  router.delete(route('chats.destroy', { chat: chat.id }))
}

async function shareChat(chat: Chat): Promise<void> {
  router.patch(route('chats.update', { chat: chat.id }), { visibility: 'public' }, { preserveState: true, preserveScroll: true })
  await navigator.clipboard.writeText(route('chats.show', { chat: chat.id }))
}

function unshareChat(chat: Chat): void {
  router.patch(route('chats.update', { chat: chat.id }), { visibility: 'private' }, { preserveState: true, preserveScroll: true })
}

function onKeydown(event: KeyboardEvent): void {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    searchOpen.value = true
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <aside
    class="flex h-full shrink-0 flex-col border-r border-border bg-background transition-[width] duration-200"
    :class="collapsed ? 'w-14' : 'w-72'"
  >
    <!-- Header: collapse toggle -->
    <div class="flex items-center p-2" :class="collapsed ? 'justify-center' : 'justify-end'">
      <button
        type="button"
        class="flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        :aria-label="collapsed ? 'Expandir barra lateral' : 'Colapsar barra lateral'"
        @click="collapsed = !collapsed"
      >
        <PanelLeft class="size-4.5" />
      </button>
    </div>

    <!-- Primary nav (ChatGPT style) -->
    <nav class="space-y-0.5 px-2">
      <Link
        :href="route('chats.index')"
        class="flex items-center gap-3 rounded-lg py-2 text-sm text-foreground transition-colors hover:bg-muted"
        :class="collapsed ? 'justify-center px-0' : 'px-3'"
        :title="collapsed ? 'Nuevo chat' : undefined"
      >
        <SquarePen class="size-4.5 shrink-0 text-muted-foreground" />
        <span v-if="!collapsed">Nuevo chat</span>
      </Link>

      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-lg py-2 text-sm text-foreground transition-colors hover:bg-muted"
        :class="collapsed ? 'justify-center px-0' : 'px-3'"
        :title="collapsed ? 'Buscar chats' : undefined"
        @click="searchOpen = true"
      >
        <Search class="size-4.5 shrink-0 text-muted-foreground" />
        <span v-if="!collapsed" class="flex-1 text-left">Buscar chats</span>
        <kbd v-if="!collapsed" class="rounded border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground">⌘K</kbd>
      </button>

      <Link
        :href="route('library')"
        class="flex items-center gap-3 rounded-lg py-2 text-sm text-foreground transition-colors hover:bg-muted"
        :class="collapsed ? 'justify-center px-0' : 'px-3'"
        :title="collapsed ? 'Biblioteca' : undefined"
      >
        <BookMarked class="size-4.5 shrink-0 text-muted-foreground" />
        <span v-if="!collapsed">Biblioteca</span>
      </Link>

      <Link
        v-if="isAdmin"
        :href="route('reports.settings.edit')"
        class="flex items-center gap-3 rounded-lg py-2 text-sm text-foreground transition-colors hover:bg-muted"
        :class="collapsed ? 'justify-center px-0' : 'px-3'"
        :title="collapsed ? 'Reportes' : undefined"
      >
        <FileText class="size-4.5 shrink-0 text-muted-foreground" />
        <span v-if="!collapsed">Reportes</span>
      </Link>
    </nav>

    <!-- History -->
    <div v-if="!collapsed" class="mt-4 flex-1 space-y-5 overflow-y-auto px-2 pb-3">
      <div v-for="group in groups" :key="group.label">
        <p class="px-3 pb-1.5 text-xs text-muted-foreground">{{ group.label }}</p>
        <ul>
          <li v-for="chat in group.chats" :key="chat.id" class="group/item relative">
            <Link
              :href="route('chats.show', { chat: chat.id })"
              class="block truncate rounded-lg px-3 py-2 pr-9 text-sm transition-colors hover:bg-muted"
              :class="chat.id === activeChatId ? 'bg-muted font-medium' : ''"
            >
              {{ chat.title }}
            </Link>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <button
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:text-foreground group-hover/item:opacity-100 data-[state=open]:opacity-100"
                  aria-label="Opciones del chat"
                >
                  <MoreHorizontal class="size-4" />
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start">
                <DropdownMenuItem v-if="chat.visibility !== 'public'" @click="shareChat(chat)">
                  <Link2 class="size-4" />
                  Compartir
                </DropdownMenuItem>
                <DropdownMenuItem v-else @click="unshareChat(chat)">
                  <Lock class="size-4" />
                  Hacer privado
                </DropdownMenuItem>
                <DropdownMenuItem variant="destructive" @click="deleteChat(chat)">
                  <Trash2 class="size-4" />
                  Eliminar
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </li>
        </ul>
      </div>

      <p v-if="groups.length === 0" class="px-3 pt-4 text-sm text-muted-foreground">
        Aún no tienes conversaciones.
      </p>
    </div>
    <div v-else class="flex-1" />

    <SearchChatsDialog v-model:open="searchOpen" :chat-history="chatHistory" />
  </aside>
</template>
