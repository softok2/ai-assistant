<script setup lang="ts">
import type { MessageSource } from '@/types'
import { FileText, Globe } from 'lucide-vue-next'
import { computed } from 'vue'
import { formatShortDate } from '@/lib/dates'

const props = defineProps<{
  sources?: MessageSource[] | null
}>()

const sources = computed<MessageSource[]>(() => props.sources ?? [])

function documentLabel(source: MessageSource): string {
  const date = formatShortDate(source.synced_at)

  return date ? `${source.title} · ${date}` : source.title
}
</script>

<template>
  <div v-if="sources.length" class="mt-4 flex flex-wrap items-center gap-2">
    <span class="text-xs text-muted-foreground">Fuentes</span>

    <template v-for="(source, index) in sources" :key="`${source.kind}-${index}`">
      <a
        v-if="source.kind === 'web' && source.url"
        :href="source.url"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex max-w-64 items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs text-foreground transition-colors hover:bg-muted"
      >
        <Globe class="size-3.5 shrink-0 text-muted-foreground" :stroke-width="1.5" />
        <span class="truncate">{{ source.title }}</span>
      </a>
      <span
        v-else
        class="inline-flex max-w-64 items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs text-foreground"
      >
        <FileText class="size-3.5 shrink-0 text-muted-foreground" :stroke-width="1.5" />
        <span class="truncate">{{ documentLabel(source) }}</span>
      </span>
    </template>
  </div>
</template>
