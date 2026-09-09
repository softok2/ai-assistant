<script setup lang="ts">
import type { KindLabels, LibraryAttachment } from './types'
import { Link } from '@inertiajs/vue3'
import { Download, FileSpreadsheet, FileText, SquareArrowOutUpRight } from 'lucide-vue-next'
import { computed } from 'vue'
import { formatShortDate } from '@/lib/dates'
import { fileExtension, formatBytes } from '@/lib/files'

const props = defineProps<{
  attachment: LibraryAttachment
  kindLabels: KindLabels
}>()

const isImage = computed(() => props.attachment.kind === 'image')

const extension = computed(() => fileExtension(props.attachment.name))

const kindLabel = computed(() => props.kindLabels[props.attachment.kind]?.label ?? '')

const meta = computed(() => [
  extension.value || kindLabel.value,
  formatBytes(props.attachment.bytes, ''),
  formatShortDate(props.attachment.created_at),
].filter(Boolean) as string[])
</script>

<template>
  <div class="group flex min-w-0 flex-col gap-2">
    <div class="rounded-2xl bg-black/[0.04] p-1.5 ring-1 ring-black/5 dark:bg-white/[0.04] dark:ring-white/5">
      <div class="relative aspect-[4/3] overflow-hidden rounded-[10px] bg-card">
        <img
          v-if="isImage"
          :src="props.attachment.url"
          :alt="props.attachment.name"
          loading="lazy"
          class="size-full object-cover"
        >
        <div
          v-else
          class="flex size-full flex-col items-center justify-center gap-2.5 bg-muted text-muted-foreground"
          :title="kindLabel"
        >
          <FileSpreadsheet
            v-if="props.attachment.kind === 'spreadsheet'"
            class="size-7"
            :stroke-width="1.5"
            aria-hidden="true"
          />
          <FileText v-else class="size-7" :stroke-width="1.5" aria-hidden="true" />
          <span class="text-[11px] font-semibold tracking-wide">{{ extension || kindLabel }}</span>
        </div>

        <div
          class="pointer-events-none absolute inset-0 flex items-end bg-gradient-to-t from-black/55 to-transparent to-60% p-2 opacity-0 transition-opacity duration-200 group-focus-within:opacity-100 group-hover:opacity-100 pointer-coarse:opacity-100"
        >
          <div class="pointer-events-auto flex w-full gap-1.5">
            <Link
              :href="route('chats.show', { chat: props.attachment.chat_id })"
              class="flex h-7.5 flex-1 items-center justify-center gap-1.5 rounded-lg bg-white/90 text-xs font-medium text-neutral-950 transition-colors hover:bg-white"
            >
              <SquareArrowOutUpRight class="size-3.5" :stroke-width="1.5" />
              Abrir chat
            </Link>
            <a
              :href="props.attachment.download_url"
              class="flex size-7.5 items-center justify-center rounded-lg bg-white/90 text-neutral-950 transition-colors hover:bg-white"
              :aria-label="`Descargar ${props.attachment.name}`"
            >
              <Download class="size-3.5" :stroke-width="1.5" />
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="min-w-0 px-1">
      <p class="truncate text-sm font-medium" :title="props.attachment.name">{{ props.attachment.name }}</p>
      <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground tabular-nums">
        <span v-for="part in meta" :key="part">{{ part }}</span>
      </p>
    </div>
  </div>
</template>
