<script setup lang="ts">
import { computed } from 'vue'
import { daysSince, formatShortDate } from '@/lib/dates'

const props = defineProps<{
  syncedAt?: string | null
  /** En móvil el chip va debajo del título, en 11px. */
  compact?: boolean
}>()

const date = computed(() => formatShortDate(props.syncedAt))
const days = computed(() => daysSince(props.syncedAt))

const fresh = computed(() => days.value !== null && days.value < 3)

/** El estado nunca se comunica solo con el color del punto. */
const age = computed<string | null>(() => {
  if (days.value === null)
    return null
  if (fresh.value)
    return 'reciente'
  if (days.value === 1)
    return 'hace 1 día'

  return `hace ${days.value} días`
})

const label = computed(() => (date.value
  ? `Datos al ${date.value}, ${age.value}`
  : 'Sin documentos indexados'))
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-2.5 py-1 text-muted-foreground"
    :class="compact ? 'text-[11px]' : 'text-xs'"
    :aria-label="label"
  >
    <span
      class="size-1.5 shrink-0 rounded-full"
      :class="date ? (fresh ? 'bg-emerald-500' : 'bg-amber-500') : 'bg-muted-foreground/50'"
      aria-hidden="true"
    />
    <template v-if="date">
      Datos al {{ date }}
      <span class="text-muted-foreground/80">· {{ age }}</span>
    </template>
    <template v-else>
      Sin documentos indexados
    </template>
  </span>
</template>
