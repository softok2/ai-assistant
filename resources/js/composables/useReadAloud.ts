import { ref } from 'vue'

function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

/**
 * ChatGPT-style "Leer en voz alta": server-side TTS (OpenAI) played
 * in the browser; calling it again while playing stops the audio.
 */
export function useReadAloud() {
  const playing = ref(false)
  const loading = ref(false)

  let player: HTMLAudioElement | null = null

  function stop(): void {
    player?.pause()
    player = null
    playing.value = false
  }

  async function toggle(messageId: string): Promise<void> {
    if (playing.value || loading.value) {
      stop()
      loading.value = false
      return
    }

    loading.value = true

    try {
      const response = await fetch(route('chat.speech', { message: messageId }), {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json' },
      })

      if (!response.ok) {
        console.error('Speech generation failed', await response.text())
        return
      }

      const { audio, mime } = await response.json()

      player = new Audio(`data:${mime};base64,${audio}`)
      player.onended = stop
      playing.value = true
      await player.play()
    }
    catch (error) {
      console.error('Speech playback failed:', error)
      stop()
    }
    finally {
      loading.value = false
    }
  }

  return { playing, loading, toggle, stop }
}
