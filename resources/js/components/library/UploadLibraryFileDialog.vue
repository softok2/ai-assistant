<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { LoaderCircle } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const props = withDefaults(defineProps<{ open: boolean, groups?: string[] }>(), { groups: () => [] })

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const fileInput = ref<HTMLInputElement | null>(null)

const form = useForm<{ group: string, file: File | null }>({
  group: '',
  file: null,
})

watch(() => props.open, (open) => {
  if (!open) {
    form.reset()
    form.clearErrors()
    if (fileInput.value)
      fileInput.value.value = ''
  }
})

function pickFile(event: Event): void {
  form.file = (event.target as HTMLInputElement).files?.[0] ?? null
}

function submit(): void {
  form.post(route('library.files.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => emit('update:open', false),
  })
}
</script>

<template>
  <Dialog :open="props.open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Subir documento</DialogTitle>
        <DialogDescription>
          El asistente lo indexa en segundo plano y sustituye al documento anterior del mismo grupo.
        </DialogDescription>
      </DialogHeader>

      <form class="grid gap-4" @submit.prevent="submit">
        <div class="grid gap-2">
          <Label for="library-group">Grupo</Label>
          <Input
            id="library-group"
            v-model="form.group"
            list="library-groups"
            placeholder="golf"
            autocomplete="off"
            :aria-invalid="Boolean(form.errors.group)"
          />
          <datalist id="library-groups">
            <option v-for="group in props.groups" :key="group" :value="group" />
          </datalist>
          <p class="text-xs text-muted-foreground">Minúsculas y guiones, p. ej. golf.</p>
          <p v-if="form.errors.group" class="text-xs text-destructive">{{ form.errors.group }}</p>
        </div>

        <div class="grid gap-2">
          <Label for="library-file">Archivo</Label>
          <input
            id="library-file"
            ref="fileInput"
            type="file"
            accept=".md,.txt,.pdf"
            :aria-invalid="Boolean(form.errors.file)"
            class="file:text-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none file:mr-3 file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:border-destructive dark:bg-input/30"
            @change="pickFile"
          >
          <p class="text-xs text-muted-foreground">Formatos .md, .txt o .pdf, hasta 10 MB.</p>
          <p v-if="form.errors.file" class="text-xs text-destructive">{{ form.errors.file }}</p>
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" @click="emit('update:open', false)">Cancelar</Button>
          <Button type="submit" :disabled="form.processing">
            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
            {{ form.processing ? 'Subiendo…' : 'Subir' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
