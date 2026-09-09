<script setup lang="ts">
import type { ChatHistory } from '@/types'
import { useMediaQuery } from '@vueuse/core'
import { watch } from 'vue'
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet'
import { useAssistantSidebar } from '@/composables/useAssistantSidebar'
import AssistantMobileBar from './AssistantMobileBar.vue'
import ChatHistorySidebar from './ChatHistorySidebar.vue'

defineProps<{
  chatHistory?: ChatHistory | null
  activeChatId?: string
  /** Título de la barra móvil por defecto, cuando la página no trae encabezado propio. */
  title?: string
}>()

const { mobileOpen, closeMobile } = useAssistantSidebar()

// Al pasar a escritorio la barra fija vuelve, así que el panel sobra.
const isDesktop = useMediaQuery('(min-width: 768px)')

watch(isDesktop, (desktop) => {
  if (desktop)
    closeMobile()
})
</script>

<template>
  <div class="flex h-dvh overflow-hidden bg-background text-foreground">
    <!-- Escritorio: barra fija, colapsable y recordada. -->
    <ChatHistorySidebar
      class="hidden md:flex"
      :chat-history="chatHistory"
      :active-chat-id="activeChatId"
    />

    <!-- Móvil: la misma barra dentro de un panel. -->
    <Sheet :open="mobileOpen" @update:open="value => { if (!value) closeMobile() }">
      <SheetContent side="left" class="w-80 p-0">
        <SheetTitle class="sr-only">Historial de chats</SheetTitle>
        <ChatHistorySidebar
          in-sheet
          :chat-history="chatHistory"
          :active-chat-id="activeChatId"
          @navigate="closeMobile"
        />
      </SheetContent>
    </Sheet>

    <main class="flex min-w-0 flex-1 flex-col">
      <!-- Las páginas con encabezado propio lo pasan aquí; el resto recibe la barra móvil. -->
      <slot name="header">
        <AssistantMobileBar :title="title" />
      </slot>

      <slot />
    </main>
  </div>
</template>
