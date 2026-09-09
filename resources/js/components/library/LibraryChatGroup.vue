<script setup lang="ts">
import type { KindLabels, LibraryChatGroup } from './types'
import { Link } from '@inertiajs/vue3'
import { ChevronRight } from 'lucide-vue-next'
import { formatRelativeDay } from '@/lib/dates'
import LibraryFileTile from './LibraryFileTile.vue'

const props = defineProps<{
  group: LibraryChatGroup
  kindLabels: KindLabels
}>()
</script>

<template>
  <section class="flex flex-col gap-3.5">
    <div class="flex items-baseline gap-3">
      <h2 class="min-w-0 truncate text-[15px] font-semibold">{{ props.group.chatTitle }}</h2>
      <span class="shrink-0 text-xs text-muted-foreground">{{ formatRelativeDay(props.group.latestAt) }}</span>
      <Link
        :href="route('chats.show', { chat: props.group.chatId })"
        class="ml-auto inline-flex shrink-0 items-center gap-1 text-[13px] font-medium text-brand-deep transition-colors hover:text-brand"
      >
        Abrir chat
        <ChevronRight class="size-3.5" :stroke-width="1.5" />
      </Link>
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-5 lg:grid-cols-4">
      <LibraryFileTile
        v-for="attachment in props.group.attachments"
        :key="attachment.path"
        :attachment="attachment"
        :kind-labels="props.kindLabels"
      />
    </div>
  </section>
</template>
