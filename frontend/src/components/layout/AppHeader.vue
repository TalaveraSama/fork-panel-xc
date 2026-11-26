<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useUiStore } from '@stores/ui'
import {
  BellIcon,
  MoonIcon,
  SunIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  Bars3Icon,
  XMarkIcon,
  UserCircleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

const notifications = ref([
  { id: 1, type: 'info', title: 'New stream added', message: 'Channel "Sports HD" has been added', time: '2 min ago', read: false },
  { id: 2, type: 'warning', title: 'High CPU usage', message: 'Server CPU usage is at 85%', time: '15 min ago', read: false },
  { id: 3, type: 'success', title: 'Backup completed', message: 'Daily backup finished successfully', time: '1 hour ago', read: true },
])

const unreadCount = computed(() => notifications.value.filter(n => !n.read).length)
const showNotifications = ref(false)
const showUserMenu = ref(false)

function markAsRead(id) {
  const notification = notifications.value.find(n => n.id === id)
  if (notification) notification.read = true
}

function markAllAsRead() {
  notifications.value.forEach(n => { n.read = true })
}

async function logout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <header class="fixed top-0 right-0 left-0 lg:left-64 h-16 z-30 bg-white/80 dark:bg-dark-950/80 backdrop-blur-md border-b border-dark-200 dark:border-dark-700 transition-colors duration-200">
    <div class="h-full px-4 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <button
          @click="uiStore.toggleSidebar"
          class="lg:hidden p-2 rounded-lg text-dark-500 hover:text-dark-900 dark:text-dark-400 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
          aria-label="Toggle menu"
        >
          <Bars3Icon v-if="!uiStore.sidebarOpen" class="w-6 h-6" />
          <XMarkIcon v-else class="w-6 h-6" />
        </button>

        <button
          @click="uiStore.toggleSidebarCollapsed"
          class="hidden lg:inline-flex p-2 rounded-lg text-dark-500 hover:text-dark-900 dark:text-dark-400 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
          :aria-label="uiStore.sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
          :title="uiStore.sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
          <ChevronRightIcon v-if="uiStore.sidebarCollapsed" class="w-5 h-5" />
          <ChevronLeftIcon v-else class="w-5 h-5" />
        </button>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="uiStore.toggleDarkMode"
          class="p-2 rounded-lg text-dark-500 hover:text-dark-900 dark:text-dark-400 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
          :aria-label="uiStore.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
        >
          <SunIcon v-if="uiStore.darkMode" class="w-5 h-5" />
          <MoonIcon v-else class="w-5 h-5" />
        </button>

        <div class="relative">
          <button
            @click="showNotifications = !showNotifications"
            class="relative p-2 rounded-lg text-dark-500 hover:text-dark-900 dark:text-dark-400 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
            aria-label="Notifications"
          >
            <BellIcon class="w-5 h-5" />
            <span v-if="unreadCount > 0" class="absolute top-1 right-1 min-w-4 h-4 px-0.5 bg-red-500 text-white text-[10px] font-semibold rounded-full flex items-center justify-center">
              {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
          </button>

          <div v-if="showNotifications" class="dropdown-menu w-80" @click.outside="showNotifications = false">
            <div class="flex items-center justify-between px-4 py-3 border-b border-dark-200 dark:border-dark-700">
              <h3 class="font-semibold text-dark-900 dark:text-white">Notifications</h3>
              <button v-if="unreadCount > 0" @click="markAllAsRead" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
                Mark all read
              </button>
            </div>
            <div class="max-h-80 overflow-y-auto scrollbar-thin">
              <button
                v-for="notification in notifications"
                :key="notification.id"
                @click="markAsRead(notification.id)"
                class="dropdown-item w-full text-left items-start p-3"
                :class="{ 'bg-primary-50/50 dark:bg-primary-900/10': !notification.read }"
              >
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-dark-900 dark:text-white">{{ notification.title }}</p>
                  <p class="text-sm text-dark-600 dark:text-dark-400 truncate">{{ notification.message }}</p>
                  <p class="text-xs text-dark-400 dark:text-dark-500 mt-1">{{ notification.time }}</p>
                </div>
                <span v-if="!notification.read" class="w-2 h-2 rounded-full bg-primary-500 flex-shrink-0 mt-2" />
              </button>
            </div>
          </div>
        </div>

        <div class="relative">
          <button
            @click="showUserMenu = !showUserMenu"
            class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
            aria-label="User menu"
          >
            <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
              <span class="text-sm font-semibold text-primary-700 dark:text-primary-300">
                {{ authStore.user?.username?.charAt(0)?.toUpperCase() || 'U' }}
              </span>
            </div>
            <span class="hidden lg:block text-sm font-medium text-dark-700 dark:text-dark-300">
              {{ authStore.user?.username }}
            </span>
          </button>

          <div v-if="showUserMenu" class="dropdown-menu w-48" @click.outside="showUserMenu = false">
            <div class="px-4 py-3 border-b border-dark-200 dark:border-dark-700">
              <p class="font-medium text-dark-900 dark:text-white truncate">{{ authStore.user?.username }}</p>
              <p class="text-xs text-dark-500 dark:text-dark-400 mt-0.5">
                {{ authStore.isAdmin ? 'Administrator' : authStore.isReseller ? 'Reseller' : 'User' }}
              </p>
            </div>
            <router-link to="/profile" class="dropdown-item">
              <UserCircleIcon class="w-4 h-4" />
              Profile
            </router-link>
            <router-link to="/settings/general" class="dropdown-item">
              <Cog6ToothIcon class="w-4 h-4" />
              Settings
            </router-link>
            <hr class="my-1 border-dark-200 dark:border-dark-700" />
            <button @click="logout" class="dropdown-item text-red-600 dark:text-red-400 w-full">
              <ArrowRightOnRectangleIcon class="w-4 h-4" />
              Logout
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>
