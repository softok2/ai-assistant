<script setup lang="ts">
import type { ReconciliationItem, ReconciliationReport } from './types'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { CircleCheck } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { formatBytes, formatTimestamp, shortId } from './format'

const props = defineProps<{ open: boolean }>()

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const report = ref<ReconciliationReport | null>(null)
const loading = ref(false)
const failed = ref(false)
const applying = ref(false)

const sections = computed(() => {
  const current = report.value
  if (!current)
    return []
  return [
    { key: 'orphans', title: 'Huérfanos', hint: 'En el store, sin fila en la base', items: current.orphans },
    { key: 'duplicates', title: 'Duplicados', hint: 'Mismo nombre repetido en el store', items: current.duplicates },
    { key: 'loose', title: 'Sueltos', hint: 'En la cuenta, fuera del store', items: current.loose },
  ] as Array<{ key: string, title: string, hint: string, items: ReconciliationItem[] }>
})

const total = computed(() => sections.value.reduce((sum, section) => sum + section.items.length, 0))

async function load(): Promise<void> {
  loading.value = true
  failed.value = false
  try {
    const { data } = await axios.get<ReconciliationReport>(route('library.reconcile.report'))
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

function apply(): void {
  applying.value = true
  router.post(route('library.reconcile.apply'), {}, {
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
          Compara lo que hay en OpenAI con los documentos registrados y borra lo que sobra.
        </DialogDescription>
      </DialogHeader>

      <div v-if="loading" class="grid gap-3" aria-live="polite">
        <Skeleton class="h-4 w-2/3" />
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
        <p class="text-xs text-muted-foreground">
          Store: {{ report.store_files }} · Cuenta: {{ report.account_files }} · Referenciados: {{ report.referenced }}
        </p>

        <div v-if="total === 0" class="flex flex-col items-center gap-2 py-6 text-center">
          <CircleCheck class="size-6 text-muted-foreground" />
          <p class="text-sm text-muted-foreground">Todo cuadra con OpenAI.</p>
        </div>

        <div v-else class="max-h-72 space-y-4 overflow-y-auto pr-1">
          <section v-for="section in sections" :key="section.key">
            <p class="mb-1 text-xs font-medium">
              {{ section.title }} ({{ section.items.length }})
            </p>
            <p class="mb-2 text-xs text-muted-foreground">{{ section.hint }}</p>
            <p v-if="!section.items.length" class="text-xs text-muted-foreground">Sin archivos.</p>
            <ul v-else class="divide-y divide-border rounded-lg border border-border">
              <li v-for="item in section.items" :key="item.id" class="px-3 py-2 text-xs">
                <p class="truncate">{{ item.filename || 'Sin nombre' }}</p>
                <p class="text-muted-foreground">
                  {{ formatBytes(item.bytes) }} · {{ formatTimestamp(item.created_at) }} · {{ shortId(item.id) }}
                </p>
              </li>
            </ul>
          </section>
        </div>
      </template>

      <DialogFooter>
        <Button variant="ghost" @click="emit('update:open', false)">Cerrar</Button>
        <Button
          v-if="report && total > 0"
          variant="destructive"
          :disabled="applying"
          @click="apply"
        >
          Borrar {{ total }} archivos de OpenAI
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
