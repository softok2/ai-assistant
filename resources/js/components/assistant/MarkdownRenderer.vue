<script setup lang="ts">
import hljs from 'highlight.js'
import VueMarkdown from 'vue-markdown-render'

const props = defineProps<{
  content?: string
}>()

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

const markdownOptions = {
  // La respuesta del modelo no es de confianza: nada de HTML crudo.
  html: false,
  breaks: true,
  linkify: true,
  typographer: true,
  highlight(str: string, lang: string) {
    if (lang && hljs.getLanguage(lang)) {
      return hljs.highlight(str, { language: lang }).value
    }
    return escapeHtml(str)
  },
}
</script>

<template>
  <div
    v-if="props.content"
    class="prose prose-zinc dark:prose-invert max-w-none min-w-0 overflow-hidden break-words prose-p:m-0 prose-code:font-mono prose-pre:border prose-pre:border-border prose-pre:rounded-md prose-pre:p-4 prose-pre:mb-1 prose-pre:bg-muted dark:prose-pre:bg-background"
  >
    <VueMarkdown
      :source="props.content"
      :options="markdownOptions"
      @ready="(md: any) => md.linkify.set({ fuzzyEmail: false })"
    />
  </div>
</template>
