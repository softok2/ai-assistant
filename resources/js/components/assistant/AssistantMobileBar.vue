<script setup lang="ts">
import { Menu } from 'lucide-vue-next'
import { useAssistantSidebar } from '@/composables/useAssistantSidebar'

withDefaults(defineProps<{
  title?: string
}>(), {
  title: 'Asistente',
})

const { openMobile } = useAssistantSidebar()
</script>

<template>
  <!--
    En móvil la barra lateral no se renderiza fija, así que cada pantalla
    necesita su propia entrada al panel de navegación.
  -->
  <header class="flex items-center gap-2 border-b border-border px-3 py-2.5 md:hidden">
    <button
      type="button"
      class="flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
      aria-label="Abrir menú del asistente"
      @click="openMobile"
    >
      <Menu class="size-5" :stroke-width="1.5" />
    </button>
    <span class="truncate text-sm font-medium text-foreground">{{ title }}</span>
  </header>
</template>
