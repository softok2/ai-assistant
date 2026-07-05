<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  name?: string | null
}>()

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12)
    return 'Buenos días'
  if (hour < 19)
    return 'Buenas tardes'
  return 'Buenas noches'
})

const firstName = computed(() => props.name?.split(' ')[0] ?? '')
</script>

<template>
  <div class="flex flex-col items-center text-center">
    <div class="relative mb-10 size-40" aria-hidden="true">
      <div class="absolute inset-0 rounded-full bg-gradient-to-br from-violet-400 via-fuchsia-200 to-orange-300 opacity-90 blur-[2px]" />
      <div class="absolute inset-2 rounded-full bg-gradient-to-tl from-orange-200/80 via-white/40 to-violet-300/80 backdrop-blur" />
    </div>

    <h1 class="text-4xl font-semibold tracking-tight text-foreground">
      {{ greeting }}<template v-if="firstName">, {{ firstName }}</template>
    </h1>
    <p class="mt-2 text-4xl font-semibold tracking-tight">
      <span class="text-foreground">¿Por dónde </span>
      <span class="bg-gradient-to-r from-violet-500 to-fuchsia-400 bg-clip-text text-transparent">empezamos?</span>
    </p>
  </div>
</template>
