<script setup lang="ts">
import type { ChatHistory, Model, SharedData } from '@/types'
import { Head, router, usePage } from '@inertiajs/vue3'
import AssistantLayout from '@/components/assistant/AssistantLayout.vue'
import ChatGreeting from '@/components/assistant/ChatGreeting.vue'
import ChatInput from '@/components/assistant/ChatInput.vue'

defineProps<{
  chatHistory?: ChatHistory | null
}>()

const sharedProps = usePage<SharedData>().props
const userName = sharedProps.auth?.user?.name ?? null
const models = (sharedProps.availableModels ?? []) as Model[]

const suggestions = [
  'Dame un resumen ejecutivo del día',
  'KPIs de Golf de esta semana',
  'Ocupación de restaurantes',
  '¿Qué experiencias siguen abiertas?',
]

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
    <div class="flex flex-1 flex-col items-center justify-center px-4">
      <div class="w-full max-w-3xl space-y-8">
        <ChatGreeting :name="userName" />

        <ChatInput :models="models" @submit="startChat" />

        <div class="flex flex-wrap justify-center gap-2">
          <button
            v-for="suggestion in suggestions"
            :key="suggestion"
            type="button"
            class="rounded-full border border-border bg-card px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
            @click="startChat(suggestion)"
          >
            {{ suggestion }}
          </button>
        </div>
      </div>
    </div>
  </AssistantLayout>
</template>
