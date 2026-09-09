<script setup lang="ts">
import type { Chat, ChatHistory, SharedData } from '@/types'
import { Link, router, usePage } from '@inertiajs/vue3'
import {
  BookMarked,
  FileText,
  Link2,
  Loader2,
  Lock,
  MoreHorizontal,
  PanelLeft,
  Pencil,
  Pin,
  PinOff,
  Search,
  SquarePen,
  Trash2,
} from 'lucide-vue-next'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
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
import { useAssistantSidebar } from '@/composables/useAssistantSidebar'
import SearchChatsDialogV2 from './SearchChatsDialogV2.vue'

const props = defineProps<{
  chatHistory?: ChatHistory | null
  activeChatId?: string
  /** Dentro del panel móvil no se colapsa ni se muestra el botón de colapsar. */
  inSheet?: boolean
}>()

const emit = defineEmits<{
  (e: 'navigate'): void
}>()

const page = usePage<SharedData>()
const { collapsed: storedCollapsed, toggleCollapsed } = useAssistantSidebar()

const collapsed = computed(() => (props.inSheet ? false : storedCollapsed.value))

const searchOpen = ref(false)
const renamingId = ref<string | null>(null)
const draftTitle = ref('')
const renameInput = ref<HTMLInputElement | null>(null)

/**
 * El input vive dentro del `v-for`, así que un `ref` normal llegaría como
 * arreglo; solo hay uno abierto a la vez y este lo guarda directo.
 */
function setRenameInput(element: unknown): void {
  renameInput.value = element instanceof HTMLInputElement ? element : null
}
const chatToDelete = ref<Chat | null>(null)
const loadingMore = ref(false)

const auth = computed(() => page.props.auth)
const isAdmin = computed(() => auth.value?.is_admin ?? false)
const userName = computed(() => auth.value?.user?.name ?? '')
const initials = computed(() => userName.value
  .split(' ')
  .filter(Boolean)
  .slice(0, 2)
  .map(part => part[0]?.toUpperCase() ?? '')
  .join(''))

const userCaption = computed(() => [auth.value?.role_label, auth.value?.club_label]
  .filter(Boolean)
  .join(' · '))

const chats = computed<Chat[]>(() => (props.chatHistory?.data ?? []) as unknown as Chat[])
const nextPage = computed(() => props.chatHistory?.next_page_url ?? null)

interface ChatGroup {
  label: string
  chats: Chat[]
}

const groups = computed<ChatGroup[]>(() => {
  const now = new Date()
  const startOfDay = (daysAgo: number): Date => {
    const day = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    day.setDate(day.getDate() - daysAgo)
    return day
  }

  const pinned: ChatGroup = { label: 'Fijados', chats: [] }
  const buckets: ChatGroup[] = [
    { label: 'Hoy', chats: [] },
    { label: 'Ayer', chats: [] },
    { label: 'Últimos 7 días', chats: [] },
    { label: 'Anteriores', chats: [] },
  ]

  for (const chat of chats.value) {
    if (chat.pinned_at) {
      pinned.chats.push(chat)
      continue
    }

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

  return [pinned, ...buckets].filter(group => group.chats.length > 0)
})

/**
 * La respuesta vuelve a la pantalla actual, así que basta con refrescar el
 * historial. Al renombrar se pide también `chat` porque el encabezado muestra
 * el título del chat abierto.
 */
function patch(chat: Chat, data: Record<string, unknown>, only: string[] = ['chatHistory']): void {
  router.patch(route('chats.update', { chat: chat.id }), data, {
    preserveState: true,
    preserveScroll: true,
    only,
  })
}

function togglePin(chat: Chat): void {
  patch(chat, { pinned: !chat.pinned_at })
}

async function startRename(chat: Chat): Promise<void> {
  renamingId.value = chat.id
  draftTitle.value = chat.title
  await nextTick()
  renameInput.value?.focus()
  renameInput.value?.select()
}

function saveRename(chat: Chat): void {
  const title = draftTitle.value.trim()
  renamingId.value = null

  if (!title || title === chat.title)
    return

  patch(chat, { title }, ['chatHistory', 'chat'])
}

function confirmDelete(): void {
  const chat = chatToDelete.value
  chatToDelete.value = null

  if (chat)
    router.delete(route('chats.destroy', { chat: chat.id }))
}

async function shareChat(chat: Chat): Promise<void> {
  patch(chat, { visibility: 'public' })
  await navigator.clipboard.writeText(route('chats.show', { chat: chat.id }))
}

function unshareChat(chat: Chat): void {
  patch(chat, { visibility: 'private' })
}

function loadMore(): void {
  const url = nextPage.value
  if (!url || loadingMore.value)
    return

  const pageNumber = new URL(url, window.location.origin).searchParams.get('page')
  loadingMore.value = true

  router.reload({
    only: ['chatHistory'],
    data: { page: pageNumber },
    onFinish: () => (loadingMore.value = false),
  })
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
    class="flex h-full min-h-0 shrink-0 flex-col border-border bg-background transition-[width] duration-200"
    :class="[collapsed ? 'w-14' : 'w-72', inSheet ? 'w-full border-r-0' : 'border-r']"
  >
    <TooltipProvider :delay-duration="200" disable-hoverable-content>
      <div v-if="!inSheet" class="flex items-center p-2" :class="collapsed ? 'justify-center' : 'justify-end'">
        <Tooltip>
          <TooltipTrigger as-child>
            <button
              type="button"
              class="flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              :aria-label="collapsed ? 'Expandir barra lateral' : 'Colapsar barra lateral'"
              @click="toggleCollapsed"
            >
              <PanelLeft class="size-4.5" :stroke-width="1.5" />
            </button>
          </TooltipTrigger>
          <TooltipContent side="right">{{ collapsed ? 'Expandir' : 'Colapsar' }}</TooltipContent>
        </Tooltip>
      </div>

      <nav class="space-y-0.5 px-2" :class="inSheet ? 'pt-2' : ''">
        <Tooltip :disabled="!collapsed">
          <TooltipTrigger as-child>
            <Link
              :href="route('chats.index')"
              class="flex items-center gap-3 rounded-lg py-2 text-sm text-foreground transition-colors hover:bg-muted"
              :class="collapsed ? 'justify-center px-0' : 'px-3'"
              @click="emit('navigate')"
            >
              <SquarePen class="size-4.5 shrink-0 text-muted-foreground" :stroke-width="1.5" />
              <span v-if="!collapsed">Nuevo chat</span>
            </Link>
          </TooltipTrigger>
          <TooltipContent side="right">Nuevo chat</TooltipContent>
        </Tooltip>

        <Tooltip :disabled="!collapsed">
          <TooltipTrigger as-child>
            <button
              type="button"
              class="flex w-full items-center gap-3 rounded-lg py-2 text-sm text-foreground transition-colors hover:bg-muted"
              :class="collapsed ? 'justify-center px-0' : 'px-3'"
              @click="searchOpen = true"
            >
              <Search class="size-4.5 shrink-0 text-muted-foreground" :stroke-width="1.5" />
              <span v-if="!collapsed" class="flex-1 text-left">Buscar chats</span>
              <kbd v-if="!collapsed" class="rounded border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground">⌘K</kbd>
            </button>
          </TooltipTrigger>
          <TooltipContent side="right">Buscar chats</TooltipContent>
        </Tooltip>

        <Tooltip :disabled="!collapsed">
          <TooltipTrigger as-child>
            <Link
              :href="route('library')"
              class="flex items-center gap-3 rounded-lg py-2 text-sm text-foreground transition-colors hover:bg-muted"
              :class="collapsed ? 'justify-center px-0' : 'px-3'"
              @click="emit('navigate')"
            >
              <BookMarked class="size-4.5 shrink-0 text-muted-foreground" :stroke-width="1.5" />
              <span v-if="!collapsed">Biblioteca</span>
            </Link>
          </TooltipTrigger>
          <TooltipContent side="right">Biblioteca</TooltipContent>
        </Tooltip>

        <Tooltip v-if="isAdmin" :disabled="!collapsed">
          <TooltipTrigger as-child>
            <Link
              :href="route('reports.settings.edit')"
              class="flex items-center gap-3 rounded-lg py-2 text-sm text-foreground transition-colors hover:bg-muted"
              :class="collapsed ? 'justify-center px-0' : 'px-3'"
              @click="emit('navigate')"
            >
              <FileText class="size-4.5 shrink-0 text-muted-foreground" :stroke-width="1.5" />
              <span v-if="!collapsed">Reportes</span>
            </Link>
          </TooltipTrigger>
          <TooltipContent side="right">Reportes</TooltipContent>
        </Tooltip>
      </nav>
    </TooltipProvider>

    <div v-if="!collapsed" class="mt-4 min-h-0 flex-1 space-y-5 overflow-y-auto px-2 pb-3">
      <div v-for="group in groups" :key="group.label">
        <p class="px-3 pb-1.5 text-xs text-muted-foreground">{{ group.label }}</p>
        <ul>
          <li v-for="chat in group.chats" :key="chat.id" class="group/item relative">
            <input
              v-if="renamingId === chat.id"
              :ref="setRenameInput"
              v-model="draftTitle"
              class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-ring"
              :aria-label="`Nuevo nombre para ${chat.title}`"
              @keydown.enter.prevent="saveRename(chat)"
              @keydown.esc="renamingId = null"
              @blur="renamingId = null"
            >
            <template v-else>
              <Link
                :href="route('chats.show', { chat: chat.id })"
                class="block truncate rounded-lg px-3 py-2 pr-9 text-sm transition-colors hover:bg-muted"
                :class="chat.id === activeChatId ? 'bg-muted font-medium' : ''"
                @click="emit('navigate')"
              >
                {{ chat.title }}
              </Link>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <button
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:text-foreground focus-visible:opacity-100 group-hover/item:opacity-100 data-[state=open]:opacity-100"
                    :aria-label="`Opciones de ${chat.title}`"
                  >
                    <MoreHorizontal class="size-4" :stroke-width="1.5" />
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                  <DropdownMenuItem @click="togglePin(chat)">
                    <PinOff v-if="chat.pinned_at" class="size-4" :stroke-width="1.5" />
                    <Pin v-else class="size-4" :stroke-width="1.5" />
                    {{ chat.pinned_at ? 'Desfijar' : 'Fijar' }}
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="startRename(chat)">
                    <Pencil class="size-4" :stroke-width="1.5" />
                    Renombrar
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="chat.visibility !== 'public'" @click="shareChat(chat)">
                    <Link2 class="size-4" :stroke-width="1.5" />
                    Compartir
                  </DropdownMenuItem>
                  <DropdownMenuItem v-else @click="unshareChat(chat)">
                    <Lock class="size-4" :stroke-width="1.5" />
                    Hacer privado
                  </DropdownMenuItem>
                  <DropdownMenuItem variant="destructive" @click="chatToDelete = chat">
                    <Trash2 class="size-4" :stroke-width="1.5" />
                    Eliminar
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </template>
          </li>
        </ul>
      </div>

      <button
        v-if="nextPage"
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        :disabled="loadingMore"
        @click="loadMore"
      >
        <Loader2 v-if="loadingMore" class="size-4 animate-spin" :stroke-width="1.5" />
        {{ loadingMore ? 'Cargando…' : 'Cargar más' }}
      </button>

      <p v-if="groups.length === 0" class="px-3 pt-4 text-sm text-muted-foreground">
        Aún no tienes conversaciones.
      </p>
    </div>
    <div v-else class="flex-1" />

    <div v-if="!collapsed && userName" class="border-t border-border p-3">
      <div class="flex items-center gap-2.5">
        <span
          class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-soft to-brand-deep text-xs font-medium text-white"
          aria-hidden="true"
        >
          {{ initials }}
        </span>
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-foreground">{{ userName }}</p>
          <p v-if="userCaption" class="truncate text-xs text-muted-foreground">{{ userCaption }}</p>
        </div>
      </div>
    </div>

    <SearchChatsDialogV2 v-model:open="searchOpen" :chat-history="chatHistory" />

    <AlertDialog :open="!!chatToDelete" @update:open="value => { if (!value) chatToDelete = null }">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Eliminar «{{ chatToDelete?.title }}»</AlertDialogTitle>
          <AlertDialogDescription>
            Se borra la conversación y todos sus mensajes. Esta acción no se puede deshacer.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="chatToDelete = null">Cancelar</AlertDialogCancel>
          <AlertDialogAction @click="confirmDelete">Eliminar</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </aside>
</template>
