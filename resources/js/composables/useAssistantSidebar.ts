import { useStorage } from '@vueuse/core'
import { ref } from 'vue'

/**
 * Estado compartido de la barra lateral: en escritorio se recuerda si está
 * colapsada, en móvil se abre como panel desde el encabezado del chat.
 */
const collapsed = useStorage('assistant-sidebar-collapsed', false)
const mobileOpen = ref(false)

export function useAssistantSidebar() {
  return {
    collapsed,
    mobileOpen,
    toggleCollapsed: (): void => {
      collapsed.value = !collapsed.value
    },
    openMobile: (): void => {
      mobileOpen.value = true
    },
    closeMobile: (): void => {
      mobileOpen.value = false
    },
  }
}
