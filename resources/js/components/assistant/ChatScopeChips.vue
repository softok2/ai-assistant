<script setup lang="ts">
import type { SourcesScope } from '@/types'
import { FileText, Globe } from 'lucide-vue-next'
import { computed } from 'vue'
import { formatShortDate } from '@/lib/dates'

const props = defineProps<{
  sources?: SourcesScope | null
}>()

const count = computed(() => props.sources?.count ?? 0)
const date = computed(() => formatShortDate(props.sources?.synced_at))

const sourcesLabel = computed(() => {
  const documents = `${count.value} ${count.value === 1 ? 'documento' : 'documentos'}`

  return date.value
    ? `Fuentes · ${documents} (al ${date.value})`
    : `Fuentes · ${documents}`
})
</script>

<template>
  <div class="flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground">
    <span>Consulto</span>
    <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-2.5 py-1">
      <FileText class="size-3.5 shrink-0" :stroke-width="1.5" />
      {{ sourcesLabel }}
    </span>
    <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-2.5 py-1">
      <Globe class="size-3.5 shrink-0" :stroke-width="1.5" />
      Web
    </span>
  </div>
</template>
