<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@stores/auth'
import { useUiStore } from '@stores/ui'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import NavItem from './NavItem.vue'

const authStore = useAuthStore()
const uiStore = useUiStore()

const isCollapsed = computed(() => uiStore.sidebarCollapsed)
const isMobileOpen = computed(() => uiStore.sidebarOpen)

const navigation = [
  { label: 'Dashboard', icon: 'home', to: { name: 'dashboard' } },
  {
    label: 'Streams', icon: 'tv', to: { name: 'streams' }, permissions: ['streams.view'],
    children: [
      { label: 'All Streams', to: { name: 'streams' }, permissions: ['streams.view'] },
      { label: 'Create Stream', to: { name: 'stream-create' }, permissions: ['streams.create'] },
    ],
  },
  {
    label: 'Movies & Series', icon: 'film', permissions: ['vod.view'],
    children: [
      { label: 'Movies', to: { name: 'movies' }, permissions: ['vod.view'] },
      { label: 'Series', to: { name: 'series' }, permissions: ['vod.view'] },
      { label: 'Add Movie', to: { name: 'movie-create' }, permissions: ['vod.create'] },
    ],
  },
  {
    label: 'Users & Lines', icon: 'users', permissions: ['users.view'],
    children: [
      { label: 'Users', to: { name: 'users' }, permissions: ['users.view'] },
      { label: 'Lines', to: { name: 'lines' }, permissions: ['lines.view'] },
      { label: 'Create User', to: { name: 'user-create' }, permissions: ['users.create'] },
    ],
  },
  {
    label: 'Resellers', icon: 'user-tie', permissions: ['resellers.view'],
    children: [
      { label: 'All Resellers', to: { name: 'resellers' }, permissions: ['resellers.view'] },
      { label: 'Create Reseller', to: { name: 'reseller-create' }, permissions: ['resellers.create'] },
    ],
  },
  {
    label: 'EPG', icon: 'calendar', permissions: ['epg.view'],
    children: [
      { label: 'Overview', to: { name: 'epg' }, permissions: ['epg.view'] },
      { label: 'Sources', to: { name: 'epg-sources' }, permissions: ['epg.sources'] },
      { label: 'Channels', to: { name: 'epg-channels' }, permissions: ['epg.channels'] },
    ],
  },
  { label: 'Bouquets', icon: 'list', to: { name: 'bouquets' }, permissions: ['bouquets.view'] },
  { label: 'Categories', icon: 'tag', to: { name: 'categories' }, permissions: ['categories.view'] },
  { label: 'Modules', icon: 'puzzle', to: { name: 'modules' }, permissions: ['modules.view'] },
  {
    label: 'Tools', icon: 'wrench', permissions: ['tools.access'], to: { name: 'tools-diagnostics' },
    children: [
      { label: 'Diagnostics', to: { name: 'tools-diagnostics' }, permissions: ['tools.diagnostics'] },
      { label: 'Database', to: { name: 'tools-database' }, permissions: ['tools.database'] },
      { label: 'Cache', to: { name: 'tools-cache' }, permissions: ['tools.cache'] },
    ],
  },
  {
    label: 'Settings', icon: 'settings', permissions: ['settings.view'], to: { name: 'settings-general' },
    children: [
      { label: 'General', to: { name: 'settings-general' }, permissions: ['settings.general'] },
      { label: 'Streaming', to: { name: 'settings-streaming' }, permissions: ['settings.streaming'] },
      { label: 'Appearance', to: { name: 'settings-appearance' }, permissions: ['settings.appearance'] },
      { label: 'Security', to: { name: 'settings-security' }, permissions: ['settings.security'] },
      { label: 'Cluster', to: { name: 'settings-cluster' }, permissions: ['settings.cluster'] },
      { label: 'Modules', to: { name: 'settings-modules' }, permissions: ['settings.modules'] },
      { label: 'Backup', to: { name: 'settings-backup' }, permissions: ['settings.backup'] },
      { label: 'Logs', to: { name: 'settings-logs' }, permissions: ['settings.logs'] },
    ],
  },
]

function hasAccess(item) {
  if (!item.permissions || item.permissions.length === 0) return true
  if (authStore.isAdmin) return true
  return item.permissions.some(p => authStore.hasPermission(p))
}

function shouldShow(item) {
  if (hasAccess(item)) return true
  return !!item.children && item.children.some(child => hasAccess(child))
}
</script>

<template>
  <aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col bg-white dark:bg-dark-900 border-r border-dark-200 dark:border-dark-700 transition-all duration-300 ease-in-out lg:translate-x-0"
    :class="[
      isCollapsed ? 'w-20' : 'w-64',
      isMobileOpen ? 'translate-x-0' : '-translate-x-full',
    ]"
  >
    <div class="flex items-center h-16 px-4 border-b border-dark-200 dark:border-dark-700" :class="isCollapsed ? 'justify-center' : 'justify-between'">
      <button
        type="button"
        class="flex items-center gap-3 min-w-0 text-left"
        :title="isCollapsed ? 'Expand sidebar' : 'Fork Panel XC'"
        @click="isCollapsed && uiStore.toggleSidebarCollapsed()"
      >
        <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
        </div>
        <span v-if="!isCollapsed" class="text-base font-bold text-dark-900 dark:text-white truncate">
          Fork Panel XC
        </span>
      </button>
      <button
        v-if="!isCollapsed"
        @click="uiStore.toggleSidebarCollapsed"
        class="p-2 rounded-lg text-dark-500 hover:text-dark-900 dark:text-dark-400 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
        aria-label="Collapse sidebar"
        title="Collapse sidebar"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>
    </div>

    <!-- Always-visible expand control when collapsed -->
    <button
      v-if="isCollapsed"
      type="button"
      @click="uiStore.toggleSidebarCollapsed"
      class="absolute -right-3 top-20 z-50 flex h-7 w-7 items-center justify-center rounded-full border border-dark-200 bg-white text-dark-600 shadow-md transition-colors hover:bg-primary-50 hover:text-primary-700 dark:border-dark-700 dark:bg-dark-900 dark:text-dark-300 dark:hover:bg-dark-800 dark:hover:text-primary-300"
      aria-label="Expand sidebar"
      title="Expand sidebar"
    >
      <ChevronRightIcon class="h-4 w-4" />
    </button>

    <nav class="flex-1 overflow-y-auto scrollbar-thin p-3 space-y-1" aria-label="Main navigation">
      <template v-for="item in navigation" :key="item.label">
        <NavItem v-if="shouldShow(item)" :item="item" :collapsed="isCollapsed" />
      </template>
    </nav>

    <div v-if="!isCollapsed" class="p-4 border-t border-dark-200 dark:border-dark-700">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
          <span class="text-sm font-semibold text-primary-700 dark:text-primary-300">
            {{ authStore.user?.username?.charAt(0)?.toUpperCase() || 'U' }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-dark-900 dark:text-white truncate">{{ authStore.user?.username || 'User' }}</p>
          <p class="text-xs text-dark-500 dark:text-dark-400 truncate">
            {{ authStore.isAdmin ? 'Administrator' : authStore.isReseller ? 'Reseller' : 'User' }}
          </p>
        </div>
      </div>
      <p class="mt-3 pt-3 border-t border-dark-200 dark:border-dark-700 text-xs text-dark-500 dark:text-dark-400 text-center">
        v1.0.0-beta
      </p>
    </div>
  </aside>

  <div
    v-if="isMobileOpen"
    @click="uiStore.closeSidebar"
    class="fixed inset-0 bg-black/50 z-30 lg:hidden animate-fade-in"
    aria-hidden="true"
  />
</template>
