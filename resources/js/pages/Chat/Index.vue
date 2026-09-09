<script setup lang="ts">
import type { ChatHistory, ChatStarter, LibraryScope, Model, SharedData } from '@/types'
import { Head, router, usePage } from '@inertiajs/vue3'
import AssistantLayout from '@/components/assistant/AssistantLayout.vue'
import ChatGreetingV2 from '@/components/assistant/ChatGreetingV2.vue'
import ChatInput from '@/components/assistant/ChatInput.vue'
import ChatScopeChips from '@/components/assistant/ChatScopeChips.vue'
import ChatStarters from '@/components/assistant/ChatStarters.vue'

defineProps<{
  chatHistory?: ChatHistory | null
  starters?: ChatStarter[] | null
  library?: LibraryScope | null
  dataFreshness?: string | null
}>()

const sharedProps = usePage<SharedData>().props
const userName = sharedProps.auth?.user?.name ?? null
const models = (sharedProps.availableModels ?? []) as Model[]

function startChat(message: string, model: string | null = null, attachments: any[] = []): void {
  router.post(route('chats.store'), {
    message,
    model,
    visibility: 'private',
    attachments,
  })
}
</script>

<template>
  <Head title="Asistente" />

  <AssistantLayout :chat-history="chatHistory">
    <div class="flex flex-1 flex-col items-center justify-center overflow-y-auto px-4 py-8">
      <div class="w-full max-w-3xl space-y-8">
        <ChatGreetingV2 :name="userName" />

        <div class="space-y-3">
          <ChatInput :models="models" @submit="startChat" />
          <ChatScopeChips :library="library" />
        </div>

        <ChatStarters :starters="starters" @select="question => startChat(question)" />
      </div>
    </div>
  </AssistantLayout>
</template>
