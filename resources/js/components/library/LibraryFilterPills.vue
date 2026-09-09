<script setup lang="ts">
import type { AttachmentKind, KindLabels, LibraryAttachment } from './types'
import { computed } from 'vue'

const props = defineProps<{
  attachments: LibraryAttachment[]
  kindLabels: KindLabels
  /** `null` es «Todos». */
  modelValue: AttachmentKind | null
}>()

const emit = defineEmits<{ 'update:modelValue': [value: AttachmentKind | null] }>()

const order: AttachmentKind[] = ['image', 'pdf', 'spreadsheet', 'document', 'other']

const pills = computed(() => {
  const counts = new Map<AttachmentKind, number>()

  for (const attachment of props.attachments)
    counts.set(attachment.kind, (counts.get(attachment.kind) ?? 0) + 1)

  const kinds = order
    .filter(kind => (counts.get(kind) ?? 0) > 0)
    .map(kind => ({
      kind,
      label: props.kindLabels[kind]?.plural ?? kind,
      count: counts.get(kind) as number,
    }))

  return [
    { kind: null as AttachmentKind | null, label: 'Todos', count: props.attachments.length },
    ...kinds,
  ]
})
</script>

<template>
  <div class="-mx-1 flex items-center gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
    <button
      v-for="pill in pills"
      :key="pill.kind ?? 'all'"
      type="button"
      class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-full border px-3 text-[13px] transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
      :class="props.modelValue === pill.kind
        ? 'border-foreground bg-foreground text-background'
        : 'border-border bg-card text-muted-foreground hover:text-foreground'"
      :aria-pressed="props.modelValue === pill.kind"
      @click="emit('update:modelValue', pill.kind)"
    >
      {{ pill.label }}
      <span class="tabular-nums" :class="props.modelValue === pill.kind ? 'opacity-70' : ''">{{ pill.count }}</span>
    </button>
  </div>
</template>
