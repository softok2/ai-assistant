<script setup lang="ts">
import type { ReconciliationItem, ReconciliationReport } from './types'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { formatBytes, formatTimestamp } from './format'

const props = defineProps<{ open: boolean }>()

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const report = ref<ReconciliationReport | null>(null)
const loading = ref(false)
const failed = ref(false)
const applying = ref(false)
/** El checkbox de reka puede valer 'indeterminate'; aquí solo interesa true. */
const includeUntagged = ref<boolean | 'indeterminate'>(false)

const totals = computed(() => {
  const current = report.value

  return [
    { key: 'store', value: current?.store_files ?? 0, label: 'en el store' },
    { key: 'account', value: current?.account_files ?? 0, label: 'en la cuenta' },
    { key: 'referenced', value: current?.referenced ?? 0, label: 'referenciados' },
    { key: 'foreign', value: current?.foreign ?? 0, label: 'de otro entorno' },
  ]
})

const sections = computed(() => {
  const current = report.value
  if (!current)
    return []
  return [
    { key: 'orphans', title: 'Huérfanos', hint: 'en OpenAI, sin fila en la base', items: current.orphans },
    { key: 'duplicates', title: 'Duplicados', hint: 'mismo nombre subido dos veces', items: current.duplicates },
    { key: 'loose', title: 'Sueltos', hint: 'en la cuenta, fuera del store', items: current.loose },
  ] as Array<{ key: string, title: string, hint: string, items: ReconciliationItem[] }>
})

const total = computed(() => sections.value.reduce((sum, section) => sum + section.items.length, 0))

async function load(): Promise<void> {
  loading.value = true
  failed.value = false
  try {
    const { data } = await axios.get<ReconciliationReport>(route('sources.reconcile.report'), {
      params: includeUntagged.value === true ? { include_untagged: 1 } : {},
    })
    report.value = data
  }
  catch {
    failed.value = true
    report.value = null
  }
  finally {
    loading.value = false
  }
}

watch(() => props.open, (open) => {
  if (open)
    void load()
})

watch(includeUntagged, () => {
  if (props.open)
    void load()
})

function apply(): void {
  applying.value = true
  router.post(route('sources.reconcile.apply'), { include_untagged: includeUntagged.value === true }, {
    preserveScroll: true,
    onFinish: () => {
      applying.value = false
      emit('update:open', false)
    },
  })
}
</script>

<template>
  <Dialog :open="props.open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Reconciliar con OpenAI</DialogTitle>
        <DialogDescription>
          Compara lo que hay en la cuenta de OpenAI con esta base. Solo toca archivos de este entorno.
        </DialogDescription>
      </DialogHeader>

      <div v-if="loading" class="grid gap-3" aria-live="polite">
        <Skeleton class="h-16 w-full" />
        <Skeleton class="h-16 w-full" />
        <Skeleton class="h-16 w-full" />
      </div>

      <div v-else-if="failed" class="grid gap-3 text-sm">
        <p class="text-destructive">No se pudo consultar OpenAI.</p>
        <div>
          <Button variant="outline" size="sm" @click="load">Reintentar</Button>
        </div>
      </div>

      <template v-else-if="report">
        <dl class="grid grid-cols-2 gap-3 rounded-xl bg-muted px-4 py-3.5 sm:grid-cols-4">
          <div v-for="item in totals" :key="item.key">
            <dt class="sr-only">{{ item.label }}</dt>
            <dd class="text-lg font-semibold tabular-nums">{{ item.value }}</dd>
            <dd class="text-xs text-muted-foreground">{{ item.label }}</dd>
          </div>
        </dl>

        <div class="max-h-72 space-y-5 overflow-y-auto pr-1">
          <section v-for="section in sections" :key="section.key" class="space-y-2">
            <div class="flex flex-wrap items-baseline gap-2">
              <p class="text-sm font-semibold">
                {{ section.title }} <span class="font-medium text-muted-foreground tabular-nums">{{ section.items.length }}</span>
              </p>
              <p class="text-xs text-muted-foreground">{{ section.hint }}</p>
            </div>

            <ul v-if="section.items.length" class="divide-y divide-border rounded-[10px] border border-border">
              <li
                v-for="item in section.items"
                :key="item.id"
                class="grid grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] items-center gap-3 px-3 py-2"
              >
                <span class="truncate text-[13px]" :title="item.filename || 'Sin nombre'">
                  {{ item.filename || 'Sin nombre' }}
                </span>
                <span class="text-xs text-muted-foreground tabular-nums">{{ formatBytes(item.bytes) }}</span>
                <span class="text-right text-xs text-muted-foreground tabular-nums">{{ formatTimestamp(item.created_at) }}</span>
              </li>
            </ul>

            <p v-else class="py-1 text-[13px] text-muted-foreground">Nada que limpiar.</p>
          </section>
        </div>
      </template>

      <div v-if="!failed" class="flex items-start gap-2.5">
        <Checkbox
          id="reconcile-include-untagged"
          v-model="includeUntagged"
          :disabled="loading || applying"
          class="mt-0.5"
        />
        <Label for="reconcile-include-untagged" class="text-[13px] leading-snug font-normal text-muted-foreground">
          Incluir archivos sin etiqueta de entorno. Úsalo solo desde producción; en local o stage borraría archivos de otros entornos.
        </Label>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="emit('update:open', false)">Cancelar</Button>
        <Button
          v-if="report && total > 0"
          variant="destructive"
          :disabled="applying"
          @click="apply"
        >
          Borrar {{ total }} archivos
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
