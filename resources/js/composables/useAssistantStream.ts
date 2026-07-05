import type { Ref } from 'vue'
import type { Message } from '@/types'
import { useStream } from '@laravel/stream-vue'
import { Role } from '@/types/enum'

interface StreamParams {
  message?: string
  model?: string | null
  attachments?: Array<{ path: string, name: string, mime: string }>
  regenerate?: boolean
  edit_message_id?: string
}

interface StreamEvent {
  type: string
  delta?: string
  message?: string
  recoverable?: boolean
}

/**
 * Streams the ClubAssistant response (Laravel AI SDK SSE protocol:
 * `data: {json}\n\n` events terminated by `data: [DONE]`).
 */
export function useAssistantStream(chatId: string, messages: Ref<Message[]>, onFinish?: () => void) {
  let buffer = ''

  const appendToAssistantMessage = (delta: string): void => {
    let current = messages.value[messages.value.length - 1]

    if (!current || current.role !== Role.ASSISTANT) {
      current = { role: Role.ASSISTANT, parts: { text: '' }, attachments: [] }
      messages.value.push(current)
    }

    current.parts.text = (current.parts.text ?? '') + delta
  }

  const failWithMessage = (): void => {
    messages.value.push({
      role: Role.ASSISTANT,
      parts: { text: 'Lo siento, ocurrió un error procesando tu solicitud. Intenta de nuevo.' },
      attachments: [],
    })
  }

  const parseChunk = (raw: string): void => {
    buffer += raw
    const events = buffer.split('\n\n')
    buffer = events.pop() ?? ''

    for (const block of events) {
      const line = block.trim()
      if (!line.startsWith('data:'))
        continue

      const payload = line.slice(5).trim()
      if (payload === '[DONE]' || payload === '')
        continue

      try {
        const event = JSON.parse(payload) as StreamEvent
        if (event.type === 'text_delta' && event.delta)
          appendToAssistantMessage(event.delta)
        else if (event.type === 'error' || event.recoverable === false)
          failWithMessage()
      }
      catch (error) {
        console.error('Failed to parse stream event:', error, payload)
      }
    }
  }

  const stream = useStream<StreamParams>(
    route('chat.stream', { chat: chatId }),
    {
      onData: parseChunk,
      onError: failWithMessage,
      onFinish,
    },
  )

  return stream
}
