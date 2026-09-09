<script setup lang="ts">
import type { AttachmentKind, KindLabels, LibraryAttachment, LibraryChatGroup as LibraryChatGroupData } from '@/components/library/types'
import type { ChatHistory } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Search } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import AssistantLayout from '@/components/assistant/AssistantLayout.vue'
import LibraryChatGroup from '@/components/library/LibraryChatGroup.vue'
import LibraryEmptyState from '@/components/library/LibraryEmptyState.vue'
import LibraryFilterPills from '@/components/library/LibraryFilterPills.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

const props = withDefaults(defineProps<{
  attachments?: LibraryAttachment[]
  kindLabels: KindLabels
  chatHistory?: ChatHistory | null
}>(), {
  attachments: () => [],
})

const search = ref('')
const kind = ref<AttachmentKind | null>(null)

/** Sin acentos ni mayúsculas, para que «cancha» encuentre «Cancha». */
function normalize(value: string): string {
  return value.normalize('NFD').replace(/[\u0300-\u036F]/g, '').toLowerCase()
}

const matches = computed(() => {
  const term = normalize(search.value.trim())

  return props.attachments.filter((attachment) => {
    if (kind.value && attachment.kind !== kind.value)
      return false

    if (!term)
      return true

    return normalize(attachment.name).includes(term) || normalize(attachment.chat_title).includes(term)
  })
})

const groups = computed<LibraryChatGroupData[]>(() => {
  const byChat = new Map<string, LibraryChatGroupData>()

  for (const attachment of matches.value) {
    const group = byChat.get(attachment.chat_id) ?? {
      chatId: attachment.chat_id,
      chatTitle: attachment.chat_title,
      latestAt: attachment.created_at,
      attachments: [],
    }

    group.attachments.push(attachment)
    byChat.set(attachment.chat_id, group)
  }

  return [...byChat.values()]
})

const filtering = computed(() => search.value.trim() !== '' || kind.value !== null)

function clearFilters(): void {
  search.value = ''
  kind.value = null
}
</script>

<template>
  <Head title="Biblioteca" />

  <AssistantLayout :chat-history="chatHistory" title="Biblioteca">
    <div class="flex-1 overflow-y-auto">
      <div class="mx-auto flex w-full max-w-5xl flex-col gap-7 px-4 py-8 md:px-10">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-[22px] font-semibold tracking-tight">Biblioteca</h1>
            <p class="mt-1 text-sm text-muted-foreground">
              Todo lo que has adjuntado en tus chats, ordenado por conversación.
            </p>
          </div>

          <div v-if="attachments.length" class="relative w-full sm:w-80">
            <Search
              class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
              :stroke-width="1.5"
              aria-hidden="true"
            />
            <Input
              v-model="search"
              type="search"
              class="h-9 pl-9"
              placeholder="Buscar archivo o chat"
              aria-label="Buscar archivo o chat"
            />
          </div>
        </header>

        <template v-if="attachments.length">
          <LibraryFilterPills
            v-model="kind"
            :attachments="attachments"
            :kind-labels="kindLabels"
          />

          <div v-if="groups.length" class="flex flex-col gap-9">
            <LibraryChatGroup
              v-for="group in groups"
              :key="group.chatId"
              :group="group"
              :kind-labels="kindLabels"
            />
          </div>

          <div v-else class="flex flex-col items-center gap-3 py-20 text-center">
            <p class="text-sm text-muted-foreground">Ningún archivo coincide con la búsqueda.</p>
            <Button v-if="filtering" variant="ghost" size="sm" @click="clearFilters">Limpiar filtros</Button>
          </div>
        </template>

        <LibraryEmptyState v-else />
      </div>
    </div>
  </AssistantLayout>
</template>
