<script setup lang="ts">
import type { Chat, ChatHistory, Message, Model, SharedData } from '@/types'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import AssistantLayout from '@/components/assistant/AssistantLayout.vue'
import ChatHeader from '@/components/assistant/ChatHeader.vue'
import ChatInput from '@/components/assistant/ChatInput.vue'
import ChatMessageList from '@/components/assistant/ChatMessageList.vue'
import { useAssistantStream } from '@/composables/useAssistantStream'
import { Role } from '@/types/enum'

const props = defineProps<{
  chat: Chat
  chatHistory?: ChatHistory | null
  pendingMessage?: string | null
  pendingAttachments?: Array<{ path: string, name: string, mime: string }> | null
  canWrite?: boolean
  dataFreshness?: string | null
}>()

const sharedProps = usePage<SharedData>().props
const models = (sharedProps.availableModels ?? []) as Model[]

const messages = ref<Message[]>([...(props.chat.messages ?? [])])

const suggestions = ref<string[]>([])

const { send, cancel, isFetching, isStreaming } = useAssistantStream(props.chat.id, messages, fetchSuggestions)

const busy = computed(() => isFetching.value || isStreaming.value)

function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function fetchSuggestions(): Promise<void> {
  if (!props.canWrite)
    return

  try {
    const response = await fetch(route('chat.suggestions', { chat: props.chat.id }), {
      method: 'POST',
      headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json' },
    })
    if (response.ok)
      suggestions.value = (await response.json()).suggestions ?? []
  }
  catch {
    suggestions.value = []
  }
}

function sendMessage(message: string, model: string | null = null, attachments: Array<{ path: string, name: string, mime: string }> = []): void {
  if (busy.value)
    return

  suggestions.value = []
  messages.value.push({ role: Role.USER, parts: { text: message }, attachments })
  send({ message, model, attachments })
}

function editMessage(messageId: string, text: string): void {
  if (busy.value)
    return

  suggestions.value = []
  const index = messages.value.findIndex(message => message.id === messageId)
  if (index !== -1)
    messages.value.splice(index)

  messages.value.push({ role: Role.USER, parts: { text }, attachments: [] })
  send({ message: text, edit_message_id: messageId })
}

function regenerate(): void {
  if (busy.value)
    return

  suggestions.value = []
  const last = messages.value[messages.value.length - 1]
  if (last?.role === Role.ASSISTANT)
    messages.value.pop()

  send({ regenerate: true })
}

onMounted(() => {
  if (props.pendingMessage) {
    sendMessage(props.pendingMessage, null, props.pendingAttachments ?? [])
  }
})
</script>

<template>
  <Head :title="chat.title" />

  <AssistantLayout :chat-history="chatHistory" :active-chat-id="chat.id">
    <template #header>
      <ChatHeader :chat="chat" :data-freshness="dataFreshness" :can-write="canWrite" />
    </template>

    <ChatMessageList
      :messages="messages"
      :chat-id="chat.id"
      :thinking="isFetching && !isStreaming"
      :streaming="isStreaming"
      :busy="busy"
      :can-write="canWrite"
      :suggestions="suggestions"
      @regenerate="regenerate"
      @edit="editMessage"
      @suggestion="text => sendMessage(text)"
    />

    <div v-if="canWrite" class="px-4 pb-4 md:px-8">
      <div class="mx-auto w-full max-w-3xl">
        <ChatInput :models="models" :busy="busy" @submit="sendMessage" @stop="cancel" />
      </div>
    </div>
    <div v-else class="px-4 pb-4 text-center text-xs text-muted-foreground">
      Vista de solo lectura. Este chat fue compartido contigo.
    </div>
  </AssistantLayout>
</template>
