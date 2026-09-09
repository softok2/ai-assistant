import type { Ref } from 'vue'
import type { ActivityEntry, ActivityStatus, Message, MessageSource } from '@/types'
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
  item_id?: string
  status?: string
  data?: Record<string, unknown>
  tool_name?: string
  tool_id?: string
  successful?: boolean
  citation?: { title?: string | null, url?: string }
}

const ACTIVITY_LABELS: Record<string, string> = {
  file_search: 'Consulté la biblioteca del club',
  web_search: 'Busqué en la web',
}

function toStatus(status: string | undefined): ActivityStatus {
  if (status === 'completed' || status === 'done')
    return 'completed'
  if (status === 'failed' || status === 'incomplete' || status === 'error')
    return 'failed'
  return 'in_progress'
}

/**
 * Streams the ClubAssistant response (Laravel AI SDK SSE protocol:
 * `data: {json}\n\n` events terminated by `data: [DONE]`).
 *
 * Además del texto, va acumulando en vivo lo que el asistente hace
 * (`parts.activity`) y las fuentes que cita (`parts.sources`); el backend
 * vuelve a calcular ambas al guardar el mensaje.
 */
export function useAssistantStream(chatId: string, messages: Ref<Message[]>, onFinish?: () => void) {
  let buffer = ''
  const activityKeys = new Map<string, number>()

  const currentAssistantMessage = (): Message => {
    let current = messages.value[messages.value.length - 1]

    if (!current || current.role !== Role.ASSISTANT) {
      current = { role: Role.ASSISTANT, parts: { text: '' }, attachments: [] }
      messages.value.push(current)
    }

    return current
  }

  const appendToAssistantMessage = (delta: string): void => {
    const current = currentAssistantMessage()
    current.parts.text = (current.parts.text ?? '') + delta
  }

  const trackActivity = (key: string, entry: ActivityEntry): void => {
    const current = currentAssistantMessage()
    const trail = current.parts.activity ?? (current.parts.activity = [])
    const index = activityKeys.get(key)

    if (index === undefined) {
      activityKeys.set(key, trail.length)
      trail.push(entry)
      return
    }

    trail[index] = entry
  }

  const trackSource = (source: MessageSource): void => {
    const current = currentAssistantMessage()
    const sources = current.parts.sources ?? (current.parts.sources = [])

    if (sources.some(existing => existing.url === source.url && existing.title === source.title))
      return

    sources.push(source)
  }

  const readProviderTool = (event: StreamEvent): void => {
    const kind = event.type === 'file_search_call'
      ? 'file_search'
      : event.type === 'web_search_call' ? 'web_search' : 'tool'

    trackActivity(`${kind}:${event.item_id ?? ''}`, {
      type: kind,
      label: ACTIVITY_LABELS[kind] ?? event.type,
      status: toStatus(event.status),
    })

    if (kind === 'file_search' && toStatus(event.status) === 'completed')
      trackSource({ kind: 'document', title: 'Biblioteca del club', url: null })
  }

  const failWithMessage = (): void => {
    messages.value.push({
      role: Role.ASSISTANT,
      parts: { text: 'Lo siento, ocurrió un error procesando tu solicitud. Intenta de nuevo.' },
      attachments: [],
    })
  }

  const handle = (event: StreamEvent): void => {
    if (event.type === 'text_delta' && event.delta) {
      appendToAssistantMessage(event.delta)
      return
    }

    if (event.type === 'error' || event.recoverable === false) {
      failWithMessage()
      return
    }

    if (event.type === 'tool_call') {
      trackActivity(`tool:${event.tool_id ?? ''}`, {
        type: 'tool',
        label: event.tool_name ?? 'Herramienta',
        status: 'in_progress',
      })
      return
    }

    if (event.type === 'tool_result') {
      trackActivity(`tool:${event.tool_id ?? ''}`, {
        type: 'tool',
        label: event.tool_name ?? 'Herramienta',
        status: event.successful === false ? 'failed' : 'completed',
      })
      return
    }

    if (event.type === 'citation' && event.citation?.url) {
      trackSource({
        kind: 'web',
        title: event.citation.title ?? event.citation.url,
        url: event.citation.url,
      })
      return
    }

    // Eventos de proveedor: `file_search_call`, `web_search_call` y demás
    // `*_call`. Los de razonamiento y los de inicio/fin se ignoran.
    if (event.type.endsWith('_call'))
      readProviderTool(event)
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
        handle(JSON.parse(payload) as StreamEvent)
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
      onFinish: () => {
        activityKeys.clear()
        onFinish?.()
      },
    },
  )

  return stream
}
