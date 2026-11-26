<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import {
  Cog6ToothIcon,
  TvIcon,
  PaintBrushIcon,
  ShieldCheckIcon,
  ServerIcon,
  PuzzlePieceIcon,
  ArchiveBoxArrowDownIcon,
  DocumentTextIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  sections: { type: Array, required: true },
})

const route = useRoute()
const authStore = useAuthStore()

const currentSection = computed(() => {
  const name = String(route.name || '')
  return name.startsWith('settings-') ? name.replace('settings-', '') : 'general'
})

const iconMap = {
  'cog-6-tooth': Cog6ToothIcon,
  tv: TvIcon,
  'paint-brush': PaintBrushIcon,
  'shield-check': ShieldCheckIcon,
  server: ServerIcon,
  'puzzle-piece': PuzzlePieceIcon,
  'archive-box-arrow-down': ArchiveBoxArrowDownIcon,
  'document-text': DocumentTextIcon,
}

function getIcon(name) {
  return iconMap[name] || Cog6ToothIcon
}

function hasAccess(section) {
  if (!section.permissions || section.permissions.length === 0) return true
  if (authStore.isAdmin) return true
  return section.permissions.some(p => authStore.hasPermission(p))
}
</script>

<template>
  <div class="card lg:sticky lg:top-24 h-fit">
    <div class="card-body py-2">
      <nav class="space-y-1" aria-label="Settings navigation">
        <template v-for="section in sections" :key="section.name">
          <router-link
            v-if="hasAccess(section)"
            :to="{ name: `settings-${section.name}` }"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="currentSection === section.name
              ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
              : 'text-dark-600 dark:text-dark-400 hover:bg-dark-100 dark:hover:bg-dark-800 hover:text-dark-900 dark:hover:text-dark-100'"
          >
            <component :is="getIcon(section.icon)" class="w-5 h-5 flex-shrink-0" />
            {{ section.label }}
          </router-link>
        </template>
      </nav>
    </div>
  </div>
</template>
