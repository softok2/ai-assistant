<script setup lang="ts">
import type { Chat } from '@/types'
import { router } from '@inertiajs/vue3'
import { Menu, Pencil } from 'lucide-vue-next'
import { nextTick, ref } from 'vue'
import { useAssistantSidebar } from '@/composables/useAssistantSidebar'
import DataFreshnessChip from './DataFreshnessChip.vue'

const props = defineProps<{
  chat: Chat
  dataFreshness?: string | null
  canWrite?: boolean
}>()

const { openMobile } = useAssistantSidebar()

const editing = ref(false)
const draft = ref('')
const input = ref<HTMLInputElement>()

async function startEdit(): Promise<void> {
  draft.value = props.chat.title
  editing.value = true
  await nextTick()
  input.value?.focus()
  input.value?.select()
}

function save(): void {
  const title = draft.value.trim()
  editing.value = false

  if (!title || title === props.chat.title)
    return

  router.patch(
    route('chats.update', { chat: props.chat.id }),
    { title },
    { preserveState: true, preserveScroll: true, only: ['chat', 'chatHistory'] },
  )
}
</script>

<template>
  <header class="flex items-start gap-2 border-b border-border px-3 py-2.5 md:items-center md:px-6">
    <button
      type="button"
      class="flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground md:hidden"
      aria-label="Abrir historial de chats"
      @click="openMobile"
    >
      <Menu class="size-5" :stroke-width="1.5" />
    </button>

    <div class="min-w-0 flex-1">
      <div class="flex min-w-0 items-center gap-1.5">
        <input
          v-if="editing"
          ref="input"
          v-model="draft"
          class="w-full max-w-md rounded-lg border border-border bg-background px-2.5 py-1 text-sm outline-none focus:ring-1 focus:ring-ring"
          aria-label="Nombre del chat"
          @keydown.enter.prevent="save"
          @keydown.esc="editing = false"
          @blur="editing = false"
        >
        <template v-else>
          <h1 class="truncate text-sm font-medium text-foreground">{{ chat.title }}</h1>
          <button
            v-if="canWrite"
            type="button"
            class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            aria-label="Renombrar chat"
            @click="startEdit"
          >
            <Pencil class="size-3.5" :stroke-width="1.5" />
          </button>
        </template>
      </div>

      <DataFreshnessChip :synced-at="dataFreshness" compact class="mt-1 md:hidden" />
    </div>

    <DataFreshnessChip :synced-at="dataFreshness" class="hidden shrink-0 md:inline-flex" />
  </header>
</template>
