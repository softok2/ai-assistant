<script setup lang="ts">
import type { ActivityEntry } from '@/types'
import { Check, Globe, Library, Loader2, TriangleAlert, Wrench } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
  activity?: ActivityEntry[] | null
}>()

const entries = computed<ActivityEntry[]>(() => props.activity ?? [])

const icons = {
  file_search: Library,
  web_search: Globe,
  tool: Wrench,
}

function label(entry: ActivityEntry): string {
  return entry.status === 'in_progress' ? 'Consultando…' : entry.label
}
</script>

<template>
  <ul v-if="entries.length" class="mb-3 space-y-1.5">
    <li
      v-for="(entry, index) in entries"
      :key="`${entry.type}-${index}`"
      class="flex items-center gap-2 text-xs text-muted-foreground"
    >
      <Loader2 v-if="entry.status === 'in_progress'" class="size-3.5 shrink-0 animate-spin" :stroke-width="1.5" />
      <TriangleAlert v-else-if="entry.status === 'failed'" class="size-3.5 shrink-0 text-amber-600" :stroke-width="1.5" />
      <Check v-else class="size-3.5 shrink-0 text-emerald-600" :stroke-width="1.5" />

      <component :is="icons[entry.type]" class="size-3.5 shrink-0 opacity-70" :stroke-width="1.5" />
      <span class="truncate">{{ label(entry) }}</span>
    </li>
  </ul>
</template>
