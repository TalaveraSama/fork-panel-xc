<script setup>
import { RouterView } from 'vue-router'
import SettingsSidebar from './components/SettingsSidebar.vue'

const settingsSections = [
  { name: 'general', label: 'General', icon: 'cog-6-tooth', permissions: ['settings.general'] },
  { name: 'streaming', label: 'Streaming', icon: 'tv', permissions: ['settings.streaming'] },
  { name: 'appearance', label: 'Appearance', icon: 'paint-brush', permissions: ['settings.appearance'] },
  { name: 'security', label: 'Security', icon: 'shield-check', permissions: ['settings.security'] },
  { name: 'cluster', label: 'Cluster', icon: 'server', permissions: ['settings.cluster'] },
  { name: 'modules', label: 'Modules', icon: 'puzzle-piece', permissions: ['settings.modules'] },
  { name: 'backup', label: 'Backup', icon: 'archive-box-arrow-down', permissions: ['settings.backup'] },
  { name: 'logs', label: 'Logs', icon: 'document-text', permissions: ['settings.logs'] },
]
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Settings</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Configure your Fork Panel XC installation</p>
    </div>

    <div class="grid lg:grid-cols-4 gap-6">
      <!-- Sidebar -->
      <div class="lg:col-span-1">
        <SettingsSidebar :sections="settingsSections" />
      </div>

      <!-- Content -->
      <div class="lg:col-span-3">
        <RouterView v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </RouterView>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>