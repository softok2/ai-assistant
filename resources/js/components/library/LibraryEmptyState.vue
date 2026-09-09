<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Paperclip, SquarePen } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-4 px-6 py-24 text-center">
    <div class="rounded-[20px] bg-black/[0.04] p-1.5 ring-1 ring-black/5 dark:bg-white/[0.04] dark:ring-white/5">
      <div class="flex size-16 items-center justify-center rounded-[14px] bg-card text-muted-foreground shadow-xs">
        <Paperclip class="size-6.5" :stroke-width="1.5" />
      </div>
    </div>

    <div class="max-w-95">
      <h2 class="text-base font-semibold">Aún no has adjuntado archivos</h2>
      <p class="mt-1.5 text-sm text-muted-foreground">
        Cuando subas una imagen, un PDF o una hoja de cálculo a un chat, quedará guardado aquí para volver a usarlo.
      </p>
    </div>

    <Button as-child size="sm">
      <Link :href="route('chats.index')">
        <SquarePen class="size-4" :stroke-width="1.5" />
        Nuevo chat
      </Link>
    </Button>
  </div>
</template>
