<script setup lang="ts">
import { computed } from 'vue'

export interface KpiItem {
  label: string
  value: string
  delta?: string | null
  tone?: 'good' | 'bad' | 'neutral'
}

export interface KpiSpec {
  items: KpiItem[]
}

const props = defineProps<{
  spec: KpiSpec
}>()

const items = computed<KpiItem[]>(() => (props.spec.items ?? []).slice(0, 4))

/** Tailwind necesita las clases completas en el archivo, no concatenadas. */
const gridClass = computed<string>(() => {
  if (items.value.length === 1)
    return 'grid-cols-1'
  if (items.value.length === 2)
    return 'grid-cols-2'
  if (items.value.length === 3)
    return 'grid-cols-2 lg:grid-cols-3'
  return 'grid-cols-2 lg:grid-cols-4'
})

const deltaClass: Record<string, string> = {
  good: 'text-emerald-700 dark:text-emerald-400',
  bad: 'text-red-700 dark:text-red-400',
  neutral: 'text-muted-foreground',
}
</script>

<template>
  <div
    v-if="items.length"
    class="my-3 grid gap-2"
    :class="gridClass"
  >
    <div
      v-for="(item, index) in items"
      :key="`${item.label}-${index}`"
      class="rounded-xl border border-border bg-card px-4 py-3"
    >
      <p class="text-xs text-muted-foreground">{{ item.label }}</p>
      <p class="mt-0.5 text-[26px] font-semibold leading-tight tabular-nums text-foreground">{{ item.value }}</p>
      <p v-if="item.delta" class="mt-0.5 text-xs" :class="deltaClass[item.tone ?? 'neutral']">
        {{ item.delta }}
      </p>
    </div>
  </div>
</template>
