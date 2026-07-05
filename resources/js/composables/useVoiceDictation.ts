import { ref } from 'vue'

function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

/**
 * ChatGPT-style dictation: record with MediaRecorder, transcribe
 * server-side (OpenAI STT) and hand the text back to the input.
 */
export function useVoiceDictation(onText: (text: string) => void) {
  const recording = ref(false)
  const transcribing = ref(false)
  const supported = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia

  let recorder: MediaRecorder | null = null
  let chunks: Blob[] = []

  async function start(): Promise<void> {
    if (!supported || recording.value)
      return

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      chunks = []
      recorder = new MediaRecorder(stream)
      recorder.ondataavailable = event => chunks.push(event.data)
      recorder.onstop = async () => {
        stream.getTracks().forEach(track => track.stop())
        await transcribe(new Blob(chunks, { type: recorder?.mimeType || 'audio/webm' }))
      }
      recorder.start()
      recording.value = true
    }
    catch (error) {
      console.error('Microphone access denied:', error)
    }
  }

  function stop(): void {
    if (!recording.value)
      return

    recording.value = false
    recorder?.stop()
  }

  async function transcribe(blob: Blob): Promise<void> {
    transcribing.value = true

    try {
      const body = new FormData()
      body.append('audio', blob, 'dictation.webm')

      const response = await fetch(route('chat.transcribe'), {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json' },
        body,
      })

      if (response.ok) {
        const { text } = await response.json()
        if (text)
          onText(text)
      }
      else {
        console.error('Transcription failed', await response.text())
      }
    }
    finally {
      transcribing.value = false
    }
  }

  return { recording, transcribing, supported, start, stop }
}
