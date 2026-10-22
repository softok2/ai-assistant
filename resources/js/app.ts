import type { DefineComponent } from 'vue'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h } from 'vue'
import { Toaster } from 'vue-sonner'
import { ZiggyVue } from 'ziggy-js'
import { initializeTheme } from './composables/useAppearance'
import '../css/app.css'
import '../css/highlight-theme.css'

// Extend Vite's ImportMetaEnv (vite/client already types env and glob)...
declare global {
  interface ImportMetaEnv {
    readonly VITE_APP_NAME: string
  }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
  title: title => `${title} - ${appName}`,
  resolve: name => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    createApp({
      render: () => h('div', [
        h(App, props),
        h(Toaster, { position: 'top-center', class: 'pointer-events-auto' }),
      ]),
    })
      .use(plugin)
      .use(ZiggyVue)
      .mount(el)
  },
  progress: {
    color: '#4B5563',
  },
})

initializeTheme()
