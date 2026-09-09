<script setup lang="ts">
import type { ChatStarter } from '@/types'
import { computed } from 'vue'

const props = defineProps<{
  /** Llega diferido desde el servidor: `undefined` mientras se calcula. */
  starters?: ChatStarter[] | null
}>()

const emit = defineEmits<{
  (e: 'select', question: string): void
}>()

const starters = computed<ChatStarter[]>(() => (props.starters ?? []).slice(0, 4))

const loading = computed(() => props.starters === undefined)
</script>

<template>
  <div v-if="loading" class="grid gap-2 sm:grid-cols-2" aria-busy="true" aria-label="Preparando sugerencias">
    <div
      v-for="placeholder in 4"
      :key="placeholder"
      class="rounded-xl border border-border bg-card px-4 py-3"
    >
      <span class="block h-3 w-16 animate-pulse rounded bg-muted" />
      <span class="mt-2 block h-4 w-4/5 animate-pulse rounded bg-muted" />
    </div>
  </div>

  <div v-else-if="starters.length" class="grid gap-2 sm:grid-cols-2">
    <button
      v-for="starter in starters"
      :key="starter.question"
      type="button"
      class="rounded-xl border border-border bg-card px-4 py-3 text-left transition-colors hover:bg-muted"
      @click="emit('select', starter.question)"
    >
      <span class="block text-xs text-muted-foreground">{{ starter.area }}</span>
      <span class="mt-0.5 block text-sm font-medium text-foreground">{{ starter.question }}</span>
    </button>
  </div>
</template>
