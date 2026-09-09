<script setup lang="ts">
import type { SourceFile } from './types'
import { ChevronRight, Trash2 } from 'lucide-vue-next'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import SourcesTable from './SourcesTable.vue'

const props = defineProps<{
  files: SourceFile[]
  busyFileId?: number | null
}>()

const emit = defineEmits<{
  purge: []
  reindex: [file: SourceFile]
  remove: [file: SourceFile]
}>()
</script>

<template>
  <Collapsible v-slot="{ open }" class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-1">
      <CollapsibleTrigger
        class="flex items-center gap-2 rounded-lg text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
      >
        <ChevronRight class="size-4 transition-transform" :class="open ? 'rotate-90' : ''" :stroke-width="1.5" />
        Caducados <span class="tabular-nums">{{ props.files.length }}</span>
      </CollapsibleTrigger>

      <p class="text-[13px] text-muted-foreground">
        Versiones anteriores que ya se reemplazaron. Se borran de OpenAI al purgar.
      </p>

      <AlertDialog>
        <AlertDialogTrigger as-child>
          <Button variant="ghost" size="sm" class="ml-auto text-destructive hover:text-destructive">
            <Trash2 class="size-4" :stroke-width="1.5" />
            Purgar caducados
          </Button>
        </AlertDialogTrigger>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Purgar documentos caducados</AlertDialogTitle>
            <AlertDialogDescription>
              Se borrarán {{ props.files.length }} documentos de OpenAI, del disco y de la base. La purga corre en segundo plano.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="emit('purge')">
              Purgar
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>

    <CollapsibleContent>
      <SourcesTable
        :files="props.files"
        show-expiry
        :busy-file-id="props.busyFileId"
        empty-message="No hay documentos caducados."
        @reindex="emit('reindex', $event)"
        @remove="emit('remove', $event)"
      />
    </CollapsibleContent>
  </Collapsible>
</template>
