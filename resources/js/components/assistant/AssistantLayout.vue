<script setup lang="ts">
import type { ChatHistory } from '@/types'
import ChatSidebar from './ChatSidebar.vue'

defineProps<{
  chatHistory?: ChatHistory | null
  activeChatId?: string
}>()
</script>

<template>
  <div class="flex h-dvh overflow-hidden bg-background text-foreground">
    <ChatSidebar :chat-history="chatHistory" :active-chat-id="activeChatId" />
    <main class="flex min-w-0 flex-1 flex-col">
      <slot />
    </main>
  </div>
</template>
