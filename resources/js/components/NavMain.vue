<script setup lang="ts">
import type { ChatHistory } from '@/types'
import { Icon } from '@iconify/vue'
import { Link, usePage, WhenVisible } from '@inertiajs/vue3'
import { computed } from 'vue'
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar'
import { useAuth } from '@/composables/useAuth'
import { useChatHistory } from '@/composables/useChatHistory'

const props = withDefaults(defineProps<{
  chatHistory?: ChatHistory
}>(), {
  chatHistory: () => ({
    data: [],
    current_page: 1,
    next_page_url: null,
    path: '',
    per_page: 25,
    from: 0,
    to: 0,
    total: 0,
    first_page_url: '',
    last_page: 1,
    last_page_url: '',
    prev_page_url: null,
    links: [],
  }),
})

const page = usePage()

const { groupedChatHistory, hasAnyHistory } = useChatHistory(props.chatHistory)

const { isGuest } = useAuth()

const mainMenuItems = [
  {
    label: 'Nuevo Chat',
    icon: 'lucide:message-circle-plus',
    href: route('chats.index'),
  },
]

const chatHistoryGroups = computed(() => [
  {
    key: 'today',
    label: 'Hoy',
    items: groupedChatHistory?.value.today,
  },
  {
    key: 'yesterday',
    label: 'Ayer',
    items: groupedChatHistory?.value.yesterday,
  },
  {
    key: 'lastSevenDays',
    label: 'Últimos 7 Días',
    items: groupedChatHistory?.value.lastSevenDays,
  },
  {
    key: 'lastThirtyDays',
    label: 'Últimos 30 Días',
    items: groupedChatHistory?.value.lastThirtyDays,
  },
  {
    key: 'older',
    label: 'Antiguos',
    items: groupedChatHistory?.value.older,
  },
].filter(group => group.items.length > 0))

function isActiveChat(chatId: number) {
  return route('chats.show', chatId, false) === page.url
}
</script>

<template>
  <div>
    <SidebarGroup>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton
            v-for="item in mainMenuItems"
            :key="item.label"
            class="flex items-center font-semibold"
          >
            <a
              :as="item.external ? 'a' : Link"
              :href="item.href"
              :target="item.target"
              :aria-label="item.label"
              class="flex items-center"
            >
              <Icon :icon="item.icon" class="w-4 h-4" />
              <span class="ml-2">{{ item.label }}</span>
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarGroup>

    <div v-if="hasAnyHistory" role="navigation" aria-label="Chat History Navigation">
      <SidebarGroup
        v-for="group in chatHistoryGroups"
        :key="group.key"
        class="px-2 py-0"
      >
        <SidebarGroupLabel v-if="!isGuest">
          {{ group.label }}
        </SidebarGroupLabel>
        <SidebarMenu>
          <SidebarMenuItem
            v-for="historyItem in group.items"
            :key="`chat-${historyItem.id}`"
          >
            <SidebarMenuButton
              as-child
              :class="{
                'bg-secondary text-secondary-foreground': isActiveChat(historyItem.id),
              }"
              :tooltip="historyItem.title"
            >
              <Link
                :prefetch="group.key === 'today' ? ['mount'] : ['mount', 'hover']"
                :cache-for="group.key === 'today' ? ['30s', '1m'] : '1m'"
                :href="route('chats.show', historyItem.id)"
                :aria-label="`Open chat: ${historyItem.title}`"
                class="block w-full"
              >
                <span class="truncate">{{ historyItem.title }}</span>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarGroup>

      <WhenVisible
        v-if="chatHistory?.next_page_url"
        :params="{
          preserveUrl: true,
          data: { page: props.chatHistory?.current_page + 1 },
          only: ['chatHistory'],
        }"
      >
        <template #fallback>
          <SidebarGroupLabel class="mt-2" role="status" aria-live="polite">
            <div>Cargar mas chats...</div>
          </SidebarGroupLabel>
        </template>
      </WhenVisible>

      <SidebarGroupLabel class="mt-2 text-muted-foreground" role="status">
        <span> Has llegado al final de tu historial de chat.</span>
      </SidebarGroupLabel>
    </div>
  </div>
</template>
