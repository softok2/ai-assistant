<script setup lang="ts">
import type { ChatHistory } from '@/types'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { FileText, Loader2, Plus, Send, X } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import AssistantLayout from '@/components/assistant/AssistantLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface Setting {
  enabled: boolean
  recipients: string[]
  frequency: string
  day_of_week: number
  hour: number
  last_sent_at: string | null
}

const props = defineProps<{
  setting: Setting
  frequencies: Array<{ value: string, label: string }>
  chatHistory?: ChatHistory | null
}>()

const page = usePage()
watch(() => page.props.flash, (flash: any) => {
  if (flash?.success)
    toast.success(flash.success)
  if (flash?.error)
    toast.error(flash.error)
}, { immediate: true, deep: true })

const form = useForm({
  enabled: props.setting.enabled,
  recipients: [...props.setting.recipients],
  frequency: props.setting.frequency,
  day_of_week: props.setting.day_of_week,
  hour: props.setting.hour,
})

const newRecipient = ref('')
const testing = ref(false)

const weekdays = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']

function addRecipient(): void {
  const email = newRecipient.value.trim()
  if (email && !form.recipients.includes(email)) {
    form.recipients.push(email)
    newRecipient.value = ''
  }
}

function removeRecipient(email: string): void {
  form.recipients = form.recipients.filter(r => r !== email)
}

function save(): void {
  form.put(route('reports.settings.update'), { preserveScroll: true })
}

function sendTest(): void {
  testing.value = true
  router.post(route('reports.settings.test'), {}, {
    preserveScroll: true,
    onFinish: () => (testing.value = false),
  })
}
</script>

<template>
  <Head title="Configuración de reportes" />

  <AssistantLayout :chat-history="chatHistory" title="Reportes">
    <div class="flex-1 overflow-y-auto">
      <div class="mx-auto w-full max-w-2xl px-4 py-8 md:px-6">
        <div class="mb-6 flex items-center gap-3">
          <FileText class="size-6 text-muted-foreground" />
          <div>
            <h1 class="text-xl font-semibold">Reporte ejecutivo automático</h1>
            <p class="text-sm text-muted-foreground">
              La IA genera un PDF con el análisis por módulos y lo envía al director según la frecuencia que elijas.
            </p>
          </div>
        </div>

        <div class="space-y-6 rounded-xl border border-border bg-card p-6">
          <!-- Enabled -->
          <label class="flex items-center justify-between gap-4">
            <div>
              <p class="font-medium">Envío automático</p>
              <p class="text-sm text-muted-foreground">Activa el reporte programado.</p>
            </div>
            <input v-model="form.enabled" type="checkbox" class="size-5 accent-primary">
          </label>

          <hr class="border-border">

          <!-- Recipients -->
          <div>
            <Label>Destinatarios</Label>
            <p class="mb-2 text-sm text-muted-foreground">Correo del director y copias opcionales.</p>
            <div class="mb-2 flex flex-wrap gap-2">
              <span
                v-for="email in form.recipients"
                :key="email"
                class="flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-sm"
              >
                {{ email }}
                <button type="button" aria-label="Quitar" @click="removeRecipient(email)">
                  <X class="size-3.5 text-muted-foreground hover:text-foreground" />
                </button>
              </span>
            </div>
            <div class="flex gap-2">
              <Input
                v-model="newRecipient"
                type="email"
                placeholder="director@club.com"
                @keydown.enter.prevent="addRecipient"
              />
              <Button type="button" variant="outline" size="icon" aria-label="Agregar" @click="addRecipient">
                <Plus class="size-4" />
              </Button>
            </div>
            <p v-if="form.errors.recipients" class="mt-1 text-xs text-destructive">{{ form.errors.recipients }}</p>
          </div>

          <hr class="border-border">

          <!-- Frequency -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
              <Label>Frecuencia</Label>
              <select v-model="form.frequency" class="mt-1 h-9 w-full rounded-md border border-border bg-background px-3 text-sm">
                <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </div>
            <div>
              <Label>{{ form.frequency === 'monthly' ? 'Día del mes' : 'Día' }}</Label>
              <select
                v-if="form.frequency !== 'monthly'"
                v-model.number="form.day_of_week"
                class="mt-1 h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
              >
                <option v-for="(day, i) in weekdays" :key="i" :value="i">{{ day }}</option>
              </select>
              <Input v-else v-model.number="form.day_of_week" type="number" min="1" max="31" class="mt-1" />
            </div>
            <div>
              <Label>Hora</Label>
              <select v-model.number="form.hour" class="mt-1 h-9 w-full rounded-md border border-border bg-background px-3 text-sm">
                <option v-for="h in 24" :key="h - 1" :value="h - 1">{{ String(h - 1).padStart(2, '0') }}:00</option>
              </select>
            </div>
          </div>

          <p v-if="setting.last_sent_at" class="text-xs text-muted-foreground">
            Último envío: {{ new Date(setting.last_sent_at).toLocaleString('es') }}
          </p>

          <hr class="border-border">

          <div class="flex items-center justify-between">
            <Button type="button" variant="outline" :disabled="testing || !form.recipients.length" @click="sendTest">
              <Loader2 v-if="testing" class="size-4 animate-spin" />
              <Send v-else class="size-4" />
              Enviar prueba ahora
            </Button>
            <Button type="button" :disabled="form.processing" @click="save">
              {{ form.processing ? 'Guardando...' : 'Guardar' }}
            </Button>
          </div>
        </div>
      </div>
    </div>
  </AssistantLayout>
</template>
