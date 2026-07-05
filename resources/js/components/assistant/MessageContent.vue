<script setup lang="ts">
import type { ChartSpec } from './ChartBlock.vue'
import { computed } from 'vue'
import ChartBlock from './ChartBlock.vue'
import MarkdownRenderer from './MarkdownRenderer.vue'

const props = defineProps<{
  content?: string
  streaming?: boolean
}>()

type Segment
  = | { kind: 'text', text: string }
    | { kind: 'chart', spec: ChartSpec }
    | { kind: 'pending-chart' }

function isChartSpec(value: unknown): value is ChartSpec {
  return !!value && typeof value === 'object'
    && Array.isArray((value as any).labels)
    && (value as any).series !== undefined
}

/**
 * Fallback for models that emit the chart JSON as a bare line instead of
 * inside a fenced block: any standalone line that parses as a chart spec
 * becomes a chart segment.
 */
function splitBareJsonCharts(text: string): Segment[] {
  const segments: Segment[] = []
  let buffer: string[] = []

  const flush = (): void => {
    if (buffer.length)
      segments.push({ kind: 'text', text: buffer.join('\n') })
    buffer = []
  }

  for (const line of text.split('\n')) {
    const trimmed = line.trim()
    if (trimmed.startsWith('{') && trimmed.endsWith('}') && trimmed.includes('"type"')) {
      try {
        const parsed = JSON.parse(trimmed)
        if (isChartSpec(parsed)) {
          flush()
          segments.push({ kind: 'chart', spec: parsed })
          continue
        }
      }
      catch {
        // not valid JSON — keep as text
      }
    }
    buffer.push(line)
  }

  flush()
  return segments
}

/**
 * Splits assistant output into markdown and ```chart blocks. While streaming,
 * an unterminated chart block renders as a placeholder instead of raw JSON.
 */
const segments = computed<Segment[]>(() => {
  const content = props.content ?? ''
  const result: Segment[] = []
  const pattern = /```chart\s*\n([\s\S]*?)```/g
  let cursor = 0
  let match: RegExpExecArray | null

  // eslint-disable-next-line no-cond-assign
  while ((match = pattern.exec(content)) !== null) {
    if (match.index > cursor)
      result.push(...splitBareJsonCharts(content.slice(cursor, match.index)))

    try {
      result.push({ kind: 'chart', spec: JSON.parse(match[1]) })
    }
    catch {
      result.push({ kind: 'text', text: match[0] })
    }

    cursor = match.index + match[0].length
  }

  let tail = content.slice(cursor)

  const openBlock = tail.lastIndexOf('```chart')
  if (openBlock !== -1 && props.streaming) {
    result.push(...splitBareJsonCharts(tail.slice(0, openBlock)))
    result.push({ kind: 'pending-chart' })
    tail = ''
  }

  if (tail)
    result.push(...splitBareJsonCharts(tail))

  return result
})
</script>

<template>
  <div>
    <template v-for="(segment, i) in segments" :key="i">
      <MarkdownRenderer v-if="segment.kind === 'text'" :content="segment.text" />
      <ChartBlock v-else-if="segment.kind === 'chart'" :spec="segment.spec" />
      <div v-else class="my-3 flex h-40 items-center justify-center rounded-xl border border-border bg-card text-sm text-muted-foreground">
        Generando gráfica...
      </div>
    </template>
  </div>
</template>
