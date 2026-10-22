<script setup lang="ts">
import type { ReasoningPart } from '@/types'
import { Brain, ChevronDown } from '@lucide/vue'
import { computed, ref } from 'vue'

const props = defineProps<{
  reasoning?: ReasoningPart | null
  /** Sigue razonando: aún no llegó la primera palabra de la respuesta. */
  thinking: boolean
}>()

const expanded = ref(false)

const summary = computed(() => props.reasoning?.summary.trim() ?? '')

const seconds = computed(() => props.reasoning?.seconds ?? 0)

// Sin `reasoning_end` (stream cancelado) no hay duración que afirmar.
const label = computed(() => {
  if (props.thinking)
    return 'Pensando…'
  return seconds.value > 0 ? `Razonó ${seconds.value} s` : 'Razonamiento'
})

interface Fragment { kind: 'heading' | 'text', text: string }

/**
 * OpenAI entrega el resumen por partes que abren con un título en negrita y
 * laravel/ai las concatena sin separador: cada `**…**` pasa a línea de título,
 * el resto queda como texto plano. No es Markdown completo a propósito.
 */
const fragments = computed<Fragment[]>(() => summary.value
  .split(/\*\*(.+?)\*\*/g)
  .map((text, index): Fragment => ({ kind: index % 2 === 1 ? 'heading' : 'text', text: text.trim() }))
  .filter(fragment => fragment.text !== ''))

/** Mientras piensa y está plegado, solo se ve lo último que escribió. */
const latest = computed(() => fragments.value.at(-1)?.text ?? '')
</script>

<template>
  <div v-if="summary" class="mb-3 text-xs text-muted-foreground">
    <button
      type="button"
      class="flex items-center gap-2 rounded-md transition-colors hover:text-foreground"
      :aria-expanded="expanded"
      @click="expanded = !expanded"
    >
      <Brain class="size-3.5 shrink-0 opacity-70" :class="{ 'animate-pulse': thinking }" :stroke-width="1.5" aria-hidden="true" />
      <span>{{ label }}</span>
      <ChevronDown class="size-3.5 shrink-0 transition-transform" :class="{ 'rotate-180': expanded }" :stroke-width="1.5" aria-hidden="true" />
    </button>

    <p v-if="thinking && !expanded" class="mt-1.5 line-clamp-2 whitespace-pre-wrap border-l pl-3">
      {{ latest }}
    </p>

    <div v-else-if="expanded" class="mt-1.5 space-y-1 border-l pl-3">
      <p
        v-for="(fragment, index) in fragments"
        :key="index"
        :class="fragment.kind === 'heading' ? 'font-medium text-foreground/80' : 'whitespace-pre-wrap'"
      >
        {{ fragment.text }}
      </p>
    </div>
  </div>
</template>
