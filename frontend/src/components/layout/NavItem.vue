<script setup>
import { computed, ref, h } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useUiStore } from '@stores/ui'
import { ChevronRightIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  item: { type: Object, required: true },
  collapsed: { type: Boolean, default: false },
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

const open = ref(true)

const hasChildren = computed(() => !!props.item.children?.length)

const isActive = computed(() => {
  if (!props.item.to) return false
  return route.name === props.item.to.name
})

const hasActiveChild = computed(() => {
  return hasChildren.value && props.item.children.some(child => route.name === child.to?.name)
})

const expanded = computed(() => open.value || hasActiveChild.value)

function hasAccess(child) {
  if (!child.permissions || child.permissions.length === 0) return true
  if (authStore.isAdmin) return true
  return child.permissions.some(p => authStore.hasPermission(p))
}

function navigate(to) {
  if (!to) return
  router.push(to)
  uiStore.closeSidebar()
}

function toggle(event) {
  if (hasChildren.value && !props.collapsed) {
    event.preventDefault()
    open.value = !open.value
  } else if (!hasChildren.value && props.item.to) {
    navigate(props.item.to)
  }
}

const iconPaths = {
  home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  tv: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
  film: 'M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z',
  users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  'user-tie': 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
  calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  list: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
  tag: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
  puzzle: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z',
  wrench: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
  settings: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
}

const Icon = (p) => h('svg', {
  class: 'w-5 h-5 flex-shrink-0',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24',
}, [h('path', {
  'stroke-linecap': 'round',
  'stroke-linejoin': 'round',
  'stroke-width': '2',
  d: iconPaths[p.name] || iconPaths.home,
})])
</script>

<template>
  <div class="group relative">
    <button
      @click="toggle"
      :class="[
        'sidebar-link w-full text-left',
        (isActive || hasActiveChild) && 'sidebar-link-active',
        collapsed && 'justify-center px-0',
      ]"
      :title="collapsed ? item.label : undefined"
      :aria-expanded="hasChildren ? expanded : undefined"
    >
      <span class="flex items-center gap-3 flex-1 min-w-0">
        <Icon :name="item.icon" />
        <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
      </span>
      <ChevronRightIcon
        v-if="hasChildren && !collapsed"
        class="w-4 h-4 text-dark-400 transition-transform duration-200"
        :class="{ 'rotate-90': expanded }"
      />
    </button>

    <div v-if="hasChildren && !collapsed && expanded" class="mt-1 ml-4 pl-4 border-l border-dark-200 dark:border-dark-700 space-y-0.5">
      <template v-for="child in item.children" :key="child.label">
        <button
          v-if="hasAccess(child)"
          @click="navigate(child.to)"
          class="sidebar-link w-full text-left py-2 text-sm"
          :class="route.name === child.to?.name && 'sidebar-link-active'"
        >
          {{ child.label }}
        </button>
      </template>
    </div>

    <!-- Collapsed flyout -->
    <div
      v-if="hasChildren && collapsed"
      class="absolute left-full top-0 ml-2 w-48 bg-white dark:bg-dark-900 border border-dark-200 dark:border-dark-700 rounded-lg shadow-lg py-1 z-50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150"
    >
      <template v-for="child in item.children" :key="child.label">
        <button v-if="hasAccess(child)" @click="navigate(child.to)" class="dropdown-item w-full text-left">
          {{ child.label }}
        </button>
      </template>
    </div>
  </div>
</template>
