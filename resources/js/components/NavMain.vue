<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { Link } from '@inertiajs/vue3'
import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar'

const mainMenuItems = [
  {
    label: 'Asistente',
    icon: 'lucide:message-circle-plus',
    href: route('chats.index'),
  },
]
</script>

<template>
  <SidebarGroup>
    <SidebarMenu>
      <SidebarMenuItem v-for="item in mainMenuItems" :key="item.label">
        <SidebarMenuButton as-child class="flex items-center font-semibold">
          <Link :href="item.href" :aria-label="item.label" class="flex items-center">
            <Icon :icon="item.icon" class="w-4 h-4" />
            <span class="ml-2">{{ item.label }}</span>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>
</template>
