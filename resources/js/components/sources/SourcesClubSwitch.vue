<script setup lang="ts">
import type { ClubOption } from './types'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const props = withDefaults(defineProps<{
  clubs: ClubOption[]
  modelValue: string
  locked?: boolean
}>(), { locked: false })

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const currentLabel = () => props.clubs.find(c => c.value === props.modelValue)?.label ?? props.modelValue
</script>

<template>
  <div v-if="locked || clubs.length <= 1" class="text-sm text-muted-foreground">
    Club: <span class="font-medium text-foreground">{{ currentLabel() }}</span>
  </div>
  <div v-else class="flex items-center gap-3">
    <Label for="sources-club" class="text-sm text-muted-foreground">Club</Label>
    <Select :model-value="modelValue" @update:model-value="(value) => emit('update:modelValue', String(value))">
      <SelectTrigger id="sources-club" class="h-9 w-56">
        <SelectValue placeholder="Elige un club" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem v-for="club in clubs" :key="club.value" :value="club.value">
          {{ club.label }}
        </SelectItem>
      </SelectContent>
    </Select>
  </div>
</template>
