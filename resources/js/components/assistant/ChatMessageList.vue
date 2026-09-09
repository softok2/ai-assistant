<script setup lang="ts">
import type { Message } from '@/types'
import { ArrowDown } from 'lucide-vue-next'
import { nextTick, ref, watch } from 'vue'
import MessageBubble from './MessageBubble.vue'

const props = defineProps<{
  messages: Message[]
  chatId?: string
  thinking?: boolean
  streaming?: boolean
  busy?: boolean
  canWrite?: boolean
  suggestions?: string[]
}>()

const emit = defineEmits<{
  (e: 'regenerate'): void
  (e: 'edit', messageId: string, text: string): void
  (e: 'suggestion', text: string): void
}>()

const container = ref<HTMLElement>()
const atBottom = ref(true)

function isNearBottom(): boolean {
  const el = container.value
  if (!el)
    return true
  return el.scrollHeight - el.scrollTop - el.clientHeight < 120
}

function onScroll(): void {
  atBottom.value = isNearBottom()
}

function scrollToBottom(smooth = false): void {
  nextTick(() => {
    container.value?.scrollTo({ top: container.value.scrollHeight, behavior: smooth ? 'smooth' : 'auto' })
  })
}

// ChatGPT behavior: follow the stream only while the user is at the bottom.
watch(
  () => [props.messages.length, props.messages[props.messages.length - 1]?.parts.text],
  ([newLength], old) => {
    const lengthChanged = !old || newLength !== old[0]
    if (lengthChanged || atBottom.value)
      scrollToBottom()
  },
  { immediate: true },
)

function isLastAssistant(index: number): boolean {
  return index === props.messages.length - 1 && props.messages[index].role === 'assistant'
}

function isStreamingMessage(index: number): boolean {
  return !!props.streaming && isLastAssistant(index)
}
</script>

<template>
  <div class="relative flex-1 overflow-hidden">
    <div ref="container" class="h-full overflow-y-auto" @scroll.passive="onScroll">
      <div class="mx-auto w-full max-w-3xl space-y-7 px-4 py-8 md:px-6">
        <MessageBubble
          v-for="(message, index) in messages"
          :key="message.id ?? `pending-${index}`"
          :message="message"
          :chat-id="chatId"
          :is-last="isLastAssistant(index)"
          :streaming="isStreamingMessage(index)"
          :busy="busy"
          :can-write="canWrite"
          @regenerate="emit('regenerate')"
          @edit="(id, text) => emit('edit', id, text)"
        />

        <div v-if="suggestions?.length && !busy" class="flex flex-wrap gap-2">
          <button
            v-for="suggestion in suggestions"
            :key="suggestion"
            type="button"
            class="rounded-full border border-border bg-card px-3.5 py-1.5 text-sm text-foreground transition-colors hover:bg-muted"
            @click="emit('suggestion', suggestion)"
          >
            {{ suggestion }}
          </button>
        </div>

        <div v-if="thinking" class="flex items-center gap-2 text-sm text-muted-foreground">
          <span class="inline-flex gap-1">
            <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:0ms]" />
            <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:150ms]" />
            <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:300ms]" />
          </span>
          Pensando…
        </div>
      </div>
    </div>

    <!-- Scroll to bottom (ChatGPT pill) -->
    <button
      v-if="!atBottom"
      type="button"
      class="absolute bottom-4 left-1/2 flex size-9 -translate-x-1/2 items-center justify-center rounded-full border border-border bg-background shadow-md transition-colors hover:bg-muted"
      aria-label="Ir al final"
      @click="scrollToBottom(true)"
    >
      <ArrowDown class="size-4" />
    </button>
  </div>
</template>
