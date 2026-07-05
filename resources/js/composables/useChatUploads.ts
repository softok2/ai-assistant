import { ref } from 'vue'

export interface ChatAttachment {
  path: string
  name: string
  mime: string
  url: string
  uploading?: boolean
}

function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

/**
 * Uploads chat attachments ahead of send (ChatGPT-style): the file is
 * stored server-side on selection and the message only references paths.
 */
export function useChatUploads() {
  const attachments = ref<ChatAttachment[]>([])
  const uploading = ref(false)

  async function upload(files: FileList | File[]): Promise<void> {
    uploading.value = true

    try {
      for (const file of Array.from(files).slice(0, 5 - attachments.value.length)) {
        const body = new FormData()
        body.append('file', file)

        const response = await fetch(route('chat.attachments.store'), {
          method: 'POST',
          headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json' },
          body,
        })

        if (!response.ok) {
          console.error('Attachment upload failed', await response.text())
          continue
        }

        attachments.value.push(await response.json())
      }
    }
    finally {
      uploading.value = false
    }
  }

  function remove(path: string): void {
    attachments.value = attachments.value.filter(attachment => attachment.path !== path)
  }

  function clear(): void {
    attachments.value = []
  }

  return { attachments, uploading, upload, remove, clear }
}
