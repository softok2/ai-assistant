<script setup lang="ts">
import type { Message } from '@/types'
import { router } from '@inertiajs/vue3'
import { Check, Copy, FileText, Pencil } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import MessageActions from './MessageActions.vue'
import MessageContent from './MessageContent.vue'

const props = defineProps<{
  message: Message
  chatId?: string
  isLast?: boolean
  streaming?: boolean
  busy?: boolean
  canWrite?: boolean
}>()

const emit = defineEmits<{
  (e: 'regenerate'): void
  (e: 'edit', messageId: string, text: string): void
}>()

const copied = ref(false)
const editing = ref(false)
const draft = ref('')

function startEdit(): void {
  draft.value = props.message.parts.text ?? ''
  editing.value = true
}

function saveEdit(): void {
  const text = draft.value.trim()
  if (!text || !props.message.id)
    return

  editing.value = false
  emit('edit', props.message.id, text)
}

const isUser = props.message.role === 'user'

const attachments = computed(() => {
  const raw = props.message.attachments
  const list = typeof raw === 'string' ? JSON.parse(raw || '[]') : (raw ?? [])
  return Array.isArray(list)
    ? list.filter((item: any) => item && typeof item === 'object' && item.path)
    : []
})

function attachmentUrl(path: string): string {
  return route('chat.attachments.show', { path })
}

async function copy(): Promise<void> {
  await navigator.clipboard.writeText(props.message.parts.text ?? '')
  copied.value = true
  setTimeout(() => (copied.value = false), 1500)
}

async function shareChat(): Promise<void> {
  if (!props.chatId)
    return

  router.patch(
    route('chats.update', { chat: props.chatId }),
    { visibility: 'public' },
    { preserveState: true, preserveScroll: true, only: [] },
  )
  await navigator.clipboard.writeText(route('chats.show', { chat: props.chatId }))
}

function vote(isUpvoted: boolean): void {
  if (!props.chatId || !props.message.id)
    return

  router.patch(
    route('chats.update', { chat: props.chatId }),
    { message_id: props.message.id, is_upvoted: isUpvoted },
    { preserveState: true, preserveScroll: true, only: [] },
  )
}
</script>

<template>
  <!-- User: soft gray bubble, right aligned (ChatGPT style) -->
  <div v-if="isUser" class="group/msg flex flex-col items-end gap-2">
    <div v-if="attachments.length" class="flex flex-wrap justify-end gap-2">
      <template v-for="attachment in attachments" :key="attachment.path">
        <img
          v-if="attachment.mime?.startsWith('image/')"
          :src="attachmentUrl(attachment.path)"
          :alt="attachment.name"
          class="max-h-44 rounded-2xl border border-border object-cover"
        >
        <a
          v-else
          :href="attachmentUrl(attachment.path)"
          target="_blank"
          class="flex items-center gap-2 rounded-xl border border-border bg-muted/40 px-3 py-2 text-xs text-foreground hover:bg-muted"
        >
          <FileText class="size-4 text-muted-foreground" />
          {{ attachment.name }}
        </a>
      </template>
    </div>

    <div v-if="editing" class="w-full max-w-[85%]">
      <textarea
        v-model="draft"
        rows="3"
        class="w-full resize-none rounded-2xl border border-border bg-muted px-4 py-3 text-[15px] outline-none focus:ring-1 focus:ring-ring"
        @keydown.enter.exact.prevent="saveEdit"
        @keydown.esc="editing = false"
      />
      <div class="mt-2 flex justify-end gap-2">
        <button type="button" class="rounded-full px-4 py-1.5 text-sm text-muted-foreground hover:bg-muted" @click="editing = false">
          Cancelar
        </button>
        <button type="button" class="rounded-full bg-primary px-4 py-1.5 text-sm text-primary-foreground hover:opacity-90" @click="saveEdit">
          Enviar
        </button>
      </div>
    </div>

    <div v-else class="flex max-w-[70%] items-center gap-1.5">
      <button
        v-if="canWrite && message.id && !busy"
        type="button"
        class="rounded p-1.5 text-muted-foreground opacity-0 transition-opacity hover:text-foreground group-hover/msg:opacity-100"
        aria-label="Editar mensaje"
        title="Editar mensaje"
        @click="startEdit"
      >
        <Pencil class="size-3.5" />
      </button>
      <button
        type="button"
        class="rounded p-1.5 text-muted-foreground opacity-0 transition-opacity hover:text-foreground group-hover/msg:opacity-100"
        aria-label="Copiar mensaje"
        title="Copiar"
        @click="copy"
      >
        <Check v-if="copied" class="size-3.5 text-green-600" />
        <Copy v-else class="size-3.5" />
      </button>
      <div class="rounded-3xl bg-muted px-4 py-2.5 text-[15px] leading-relaxed text-foreground">
        <p class="whitespace-pre-wrap break-words">{{ message.parts.text }}</p>
      </div>
    </div>
  </div>

  <!-- Assistant: plain text, no bubble (ChatGPT style) -->
  <div v-else class="group/msg w-full">
    <div class="text-[15px] leading-relaxed">
      <MessageContent :content="message.parts.text" :streaming="streaming" />
      <span v-if="streaming" class="ml-0.5 inline-block size-3 animate-pulse rounded-full bg-foreground align-baseline" aria-hidden="true" />
    </div>

    <MessageActions
      v-if="!streaming"
      :message-id="message.id"
      :is-upvoted="message.is_upvoted"
      :can-write="canWrite"
      :can-regenerate="!!(isLast && !busy && canWrite)"
      :visible="isLast"
      @copy="copy"
      @vote="vote"
      @share="shareChat"
      @regenerate="emit('regenerate')"
    />
  </div>
</template>
