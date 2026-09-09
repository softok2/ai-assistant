<script setup lang="ts">
import type { SourcesHealth } from './types'
import { computed } from 'vue'
import { formatRelativeDayName, formatTime } from '@/lib/dates'

const props = withDefaults(defineProps<{
  health: SourcesHealth
  syncRunning?: boolean
}>(), { syncRunning: false })

const indexedValue = computed(() => `${props.health.indexed} de ${props.health.total}`)

const indexedDetail = computed(() => {
  const day = formatRelativeDayName(props.health.latest_sync_at)

  if (!day)
    return 'Sin sincronizar todavía'

  return `Actualizado ${day} a las ${formatTime(props.health.latest_sync_at)}`
})

const scheduleDetail = computed(() => {
  if (props.syncRunning)
    return 'Sincronizando ahora…'

  const next = formatTime(props.health.schedule.next_run_at)
  const window = props.health.schedule.window

  return next ? `${window}, próxima a las ${next}` : window
})

const openAiDetail = computed(() => {
  const parts: string[] = []

  parts.push(props.health.vector_store_suffix
    ? `Store …${props.health.vector_store_suffix}`
    : 'Sin store configurado')

  if (props.health.expired_count > 0) {
    parts.push(props.health.expired_count === 1
      ? '1 caducado por purgar'
      : `${props.health.expired_count} caducados por purgar`)
  }

  return parts.join(', ')
})

const columns = computed(() => [
  { key: 'indexed', label: 'Fuentes indexadas', value: indexedValue.value, detail: indexedDetail.value },
  { key: 'schedule', label: 'Sincronización automática', value: props.health.schedule.every, detail: scheduleDetail.value },
  { key: 'openai', label: 'OpenAI', value: `entorno ${props.health.environment}`, detail: openAiDetail.value },
])
</script>

<template>
  <div class="rounded-[18px] bg-black/[0.04] p-1.5 ring-1 ring-black/5 dark:bg-white/[0.04] dark:ring-white/5">
    <dl class="grid gap-4 rounded-xl bg-card px-0 py-4 shadow-xs sm:grid-cols-3 sm:gap-0">
      <div
        v-for="(column, index) in columns"
        :key="column.key"
        class="px-6"
        :class="index > 0 ? 'sm:border-l sm:border-border' : ''"
      >
        <dt class="text-xs text-muted-foreground">{{ column.label }}</dt>
        <dd class="mt-1 text-xl font-semibold tracking-tight tabular-nums">{{ column.value }}</dd>
        <dd class="mt-0.5 text-[13px] text-muted-foreground tabular-nums">{{ column.detail }}</dd>
      </div>
    </dl>
  </div>
</template>
