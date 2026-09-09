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

/** Solo el primer nombre: «Buenas tardes, Ana». */
const firstName = computed(() => props.name?.trim().split(/\s+/)[0] ?? '')

interface OrbSpark {
  top: string
  left: string
  size: number
  dx: string
  dy: string
  duration: string
  delay: string
}

const sparks: OrbSpark[] = [
  { top: '32%', left: '40%', size: 4, dx: '6px', dy: '-8px', duration: '3.2s', delay: '0s' },
  { top: '55%', left: '28%', size: 3, dx: '-5px', dy: '-10px', duration: '4.1s', delay: '0.6s' },
  { top: '44%', left: '62%', size: 5, dx: '8px', dy: '-6px', duration: '3.6s', delay: '1.2s' },
  { top: '66%', left: '52%', size: 3, dx: '4px', dy: '-12px', duration: '4.4s', delay: '1.8s' },
  { top: '38%', left: '75%', size: 3, dx: '-6px', dy: '-7px', duration: '3.9s', delay: '2.4s' },
  { top: '72%', left: '36%', size: 4, dx: '7px', dy: '-9px', duration: '3.4s', delay: '3s' },
  { top: '25%', left: '58%', size: 3, dx: '-4px', dy: '-6px', duration: '4.6s', delay: '1.5s' },
  { top: '58%', left: '70%', size: 4, dx: '5px', dy: '-11px', duration: '3.8s', delay: '0.9s' },
]
</script>

<template>
  <div class="flex flex-col items-center text-center">
    <div class="orb relative mb-10 size-40" aria-hidden="true">
      <div class="absolute inset-0 rounded-full bg-gradient-to-br from-brand-soft via-brand to-brand-deep opacity-90 blur-[2px]" />
      <div class="orb-swirl absolute inset-0 overflow-hidden rounded-full" />
      <div class="absolute inset-2 rounded-full bg-gradient-to-tl from-brand-deep/55 via-brand/50 to-brand-glow/90 backdrop-blur" />
      <span
        v-for="(spark, index) in sparks"
        :key="index"
        class="orb-spark absolute rounded-full"
        :style="{
          'top': spark.top,
          'left': spark.left,
          'width': `${spark.size}px`,
          'height': `${spark.size}px`,
          '--spark-dx': spark.dx,
          '--spark-dy': spark.dy,
          '--spark-duration': spark.duration,
          '--spark-delay': spark.delay,
        }"
      />
    </div>

    <h1 class="text-4xl font-semibold tracking-tight text-foreground">
      {{ greeting }}<template v-if="firstName">
        , {{ firstName }}
      </template>
    </h1>
    <p class="mt-2 text-4xl font-semibold tracking-tight">
      <span class="text-foreground">¿Por dónde </span>
      <span class="bg-gradient-to-r from-brand to-brand-accent bg-clip-text text-transparent">empezamos?</span>
    </p>
  </div>
</template>

<style scoped>
.orb {
  animation: orb-float 7s ease-in-out infinite;
}

@keyframes orb-float {
  0%,
  100% {
    transform: translateY(0) scale(1);
  }
  50% {
    transform: translateY(-8px) scale(1.03);
  }
}

.orb-swirl::before {
  content: '';
  position: absolute;
  inset: -40%;
  background:
    radial-gradient(circle at 30% 75%, oklch(0.45 0.13 250 / 0.8), transparent 45%),
    radial-gradient(circle at 75% 30%, oklch(0.88 0.06 230 / 0.7), transparent 40%);
  animation: orb-swirl 9s ease-in-out infinite alternate;
}

@keyframes orb-swirl {
  0% {
    transform: rotate(0deg) scale(1);
  }
  100% {
    transform: rotate(60deg) scale(1.15);
  }
}

.orb-spark {
  background: oklch(0.97 0.02 230);
  box-shadow: 0 0 6px 1px oklch(0.85 0.08 235 / 0.9);
  opacity: 0;
  animation: orb-spark-twinkle var(--spark-duration) ease-in-out var(--spark-delay) infinite;
}

@keyframes orb-spark-twinkle {
  0%,
  100% {
    opacity: 0;
    transform: translate(0, 0) scale(0.6);
  }
  20% {
    opacity: 1;
  }
  50% {
    opacity: 0.9;
    transform: translate(var(--spark-dx), var(--spark-dy)) scale(1);
  }
  80% {
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .orb,
  .orb-swirl::before,
  .orb-spark {
    animation: none;
  }

  .orb-spark {
    opacity: 0.7;
  }
}
</style>
