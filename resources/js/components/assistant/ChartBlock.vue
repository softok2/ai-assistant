<script setup lang="ts">
import { computed } from 'vue'

export interface ChartSpec {
  type?: string
  title?: string
  labels: string[]
  series: unknown
}

const props = defineProps<{
  spec: ChartSpec
}>()

interface NormalSeries { name?: string, data: number[] }

/**
 * Models emit series in loose shapes: [{name,data}], [[1,2,3]] or [1,2,3].
 * Normalize everything to [{name?, data[]}].
 */
function normalizeSeries(raw: unknown): NormalSeries[] {
  if (!Array.isArray(raw))
    return []

  if (raw.every(item => typeof item === 'number'))
    return [{ data: raw as number[] }]

  return raw
    .map((item): NormalSeries | null => {
      if (Array.isArray(item) && item.every(v => typeof v === 'number'))
        return { data: item }
      if (item && typeof item === 'object' && Array.isArray((item as any).data))
        return { name: (item as any).name, data: (item as any).data }
      return null
    })
    .filter((item): item is NormalSeries => item !== null)
}

const W = 640
const H = 260
const PAD = { top: 16, right: 16, bottom: 34, left: 44 }

const type = computed(() => {
  const raw = (props.spec.type ?? 'bar').toLowerCase()
  if (['donut', 'doughnut', 'pie'].includes(raw))
    return 'donut'
  return raw === 'line' ? 'line' : 'bar'
})
const labels = computed(() => props.spec.labels ?? [])
const series = computed(() => normalizeSeries(props.spec.series).slice(0, 4))
const showLegend = computed(() => series.value.length >= 2)

const maxValue = computed(() => {
  const values = series.value.flatMap(s => s.data)
  const max = Math.max(...values, 0)
  return max === 0 ? 1 : max * 1.12
})

const yTicks = computed(() => {
  const count = 4
  return Array.from({ length: count + 1 }, (_, i) => {
    const value = (maxValue.value / count) * i
    const y = H - PAD.bottom - ((H - PAD.top - PAD.bottom) * value) / maxValue.value
    return { y, value: Math.round(value) }
  })
})

function xBand(index: number): { x: number, width: number } {
  const innerW = W - PAD.left - PAD.right
  const step = innerW / Math.max(labels.value.length, 1)
  return { x: PAD.left + step * index, width: step }
}

function yPos(value: number): number {
  return H - PAD.bottom - ((H - PAD.top - PAD.bottom) * value) / maxValue.value
}

const bars = computed(() => {
  const groups = series.value.length
  return labels.value.flatMap((label, i) => {
    const band = xBand(i)
    const slot = (band.width * 0.66) / groups
    const start = band.x + band.width * 0.17
    return series.value.map((s, si) => {
      const value = s.data[i] ?? 0
      const y = yPos(value)
      return {
        key: `${i}-${si}`,
        x: start + slot * si + 1,
        y,
        width: Math.max(slot - 2, 3),
        height: Math.max(H - PAD.bottom - y, 0),
        value,
        label,
        seriesIndex: si,
        seriesName: s.name ?? '',
      }
    })
  })
})

const showBarLabels = computed(() => series.value.length === 1 && labels.value.length <= 8)

const linePaths = computed(() => series.value.map((s, si) => {
  const points = labels.value.map((label, i) => {
    const band = xBand(i)
    return { x: band.x + band.width / 2, y: yPos(s.data[i] ?? 0), value: s.data[i] ?? 0, label }
  })
  return {
    seriesIndex: si,
    seriesName: s.name ?? '',
    d: points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '),
    points,
  }
}))

const donutSlices = computed(() => {
  const data = series.value[0]?.data ?? []
  const total = data.reduce((sum, v) => sum + v, 0) || 1
  const cx = W / 2
  const cy = (H - 10) / 2
  const r = 88
  let angle = -Math.PI / 2

  return data.slice(0, 6).map((value, i) => {
    const sweep = (value / total) * Math.PI * 2
    const x1 = cx + r * Math.cos(angle)
    const y1 = cy + r * Math.sin(angle)
    angle += sweep
    const x2 = cx + r * Math.cos(angle)
    const y2 = cy + r * Math.sin(angle)
    const largeArc = sweep > Math.PI ? 1 : 0

    return {
      d: `M${cx},${cy} L${x1.toFixed(1)},${y1.toFixed(1)} A${r},${r} 0 ${largeArc} 1 ${x2.toFixed(1)},${y2.toFixed(1)} Z`,
      value,
      pct: Math.round((value / total) * 100),
      label: labels.value[i] ?? '',
      seriesIndex: i,
    }
  })
})
</script>

<template>
  <figure class="viz-root my-3 rounded-xl border border-border bg-card p-4">
    <figcaption v-if="spec.title" class="mb-2 text-sm font-medium text-foreground">
      {{ spec.title }}
    </figcaption>

    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" :aria-label="spec.title ?? 'Gráfica'">
      <!-- Grid + axis (bar/line) -->
      <template v-if="type !== 'donut'">
        <g>
          <line
            v-for="tick in yTicks" :key="tick.y"
            :x1="PAD.left" :x2="W - PAD.right" :y1="tick.y" :y2="tick.y"
            class="viz-grid"
          />
          <text
            v-for="tick in yTicks" :key="`t${tick.y}`"
            :x="PAD.left - 8" :y="tick.y + 4" text-anchor="end" class="viz-tick"
          >{{ tick.value }}</text>
        </g>

        <text
          v-for="(label, i) in labels" :key="`x${i}`"
          :x="xBand(i).x + xBand(i).width / 2" :y="H - PAD.bottom + 18"
          text-anchor="middle" class="viz-tick"
        >{{ label }}</text>
      </template>

      <!-- Bars -->
      <template v-if="type === 'bar'">
        <g v-for="bar in bars" :key="bar.key">
          <rect
            :x="bar.x" :y="bar.y" :width="bar.width" :height="bar.height"
            rx="4" :class="`viz-s${bar.seriesIndex + 1}`"
          >
            <title>{{ bar.label }}{{ bar.seriesName ? ` · ${bar.seriesName}` : '' }}: {{ bar.value }}</title>
          </rect>
          <text
            v-if="showBarLabels"
            :x="bar.x + bar.width / 2" :y="bar.y - 6" text-anchor="middle" class="viz-value"
          >{{ bar.value }}</text>
        </g>
      </template>

      <!-- Lines -->
      <template v-else-if="type === 'line'">
        <g v-for="line in linePaths" :key="line.seriesIndex">
          <path :d="line.d" fill="none" stroke-width="2" :class="`viz-s${line.seriesIndex + 1} viz-stroke`" />
          <circle
            v-for="(p, pi) in line.points" :key="pi"
            :cx="p.x" :cy="p.y" r="4" :class="`viz-s${line.seriesIndex + 1} viz-dot`"
          >
            <title>{{ p.label }}{{ line.seriesName ? ` · ${line.seriesName}` : '' }}: {{ p.value }}</title>
          </circle>
        </g>
      </template>

      <!-- Donut -->
      <template v-else>
        <path
          v-for="(slice, i) in donutSlices" :key="i"
          :d="slice.d" :class="`viz-s${slice.seriesIndex + 1} viz-slice`"
        >
          <title>{{ slice.label }}: {{ slice.value }} ({{ slice.pct }}%)</title>
        </path>
        <circle :cx="W / 2" :cy="(H - 10) / 2" r="52" class="viz-hole" />
      </template>
    </svg>

    <!-- Legend: identity via dot + text tokens -->
    <div v-if="showLegend || type === 'donut'" class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
      <template v-if="type === 'donut'">
        <span v-for="(slice, i) in donutSlices" :key="i" class="flex items-center gap-1.5 text-xs text-muted-foreground">
          <span :class="`viz-s${slice.seriesIndex + 1}`" class="viz-swatch" />
          {{ slice.label }} · {{ slice.pct }}%
        </span>
      </template>
      <template v-else>
        <span v-for="(s, si) in series" :key="si" class="flex items-center gap-1.5 text-xs text-muted-foreground">
          <span :class="`viz-s${si + 1}`" class="viz-swatch" />
          {{ s.name ?? `Serie ${si + 1}` }}
        </span>
      </template>
    </div>
  </figure>
</template>

<style scoped>
/* Reference dataviz palette — categorical slots, light mode */
.viz-root {
  --viz-1: #2a78d6;
  --viz-2: #1baf7a;
  --viz-3: #eda100;
  --viz-4: #008300;
  --viz-grid: color-mix(in oklab, currentColor 12%, transparent);
}

/* Dark mode steps of the same hues (validated for dark surfaces) */
.dark .viz-root,
:root.dark .viz-root {
  --viz-1: #3987e5;
  --viz-2: #199e70;
  --viz-3: #c98500;
  --viz-4: #008300;
}

.viz-s1 { fill: var(--viz-1); color: var(--viz-1); }
.viz-s2 { fill: var(--viz-2); color: var(--viz-2); }
.viz-s3 { fill: var(--viz-3); color: var(--viz-3); }
.viz-s4 { fill: var(--viz-4); color: var(--viz-4); }

.viz-stroke { fill: none; stroke: currentColor; }
.viz-dot { stroke: var(--card); stroke-width: 2; }
.viz-slice { stroke: var(--card); stroke-width: 2; }
.viz-hole { fill: var(--card); }
.viz-grid { stroke: var(--border); stroke-width: 1; }
.viz-tick { fill: var(--muted-foreground); font-size: 11px; }
.viz-value { fill: var(--foreground); font-size: 11px; font-weight: 500; }
.viz-swatch { display: inline-block; width: 8px; height: 8px; border-radius: 9999px; background: currentColor; }
</style>
