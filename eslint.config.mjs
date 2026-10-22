import antfu from '@antfu/eslint-config'
import oxlint from 'eslint-plugin-oxlint'

export default antfu({
  vue: true,
  typescript: true,
  formatters: {
    css: true,
    html: true,
    // Markdown formatting disabled: prettier rewrote prompt text sent to the model
    // (``` fences in resources/prompts/*.md became `) and broke README blockquotes.
    markdown: false,
  },
  // composer.json is owned by Composer (4-space indent); antfu's JSON style reindented it.
  ignores: ['composer.json', 'storage/**/*', '**/*.{yaml,yml,php}', 'resources/js/Components/shadcn/**/*', 'public/**/*'],
}, ...oxlint.buildFromOxlintConfigFile('./.oxlintrc.json'))
