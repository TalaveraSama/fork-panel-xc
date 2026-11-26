<script setup>
import { RouterView } from 'vue-router'
import AppHeader from '@components/layout/AppHeader.vue'
import AppSidebar from '@components/layout/AppSidebar.vue'
</script>

<template>
  <div class="min-h-screen bg-dark-50 dark:bg-dark-950">
    <AppSidebar />

    <div class="lg:pl-64">
      <AppHeader />

      <main class="pt-16 min-h-[calc(100vh-4rem)] pb-8">
        <div class="p-6">
          <RouterView v-slot="{ Component }">
            <transition name="fade" mode="out-in">
              <component :is="Component" />
            </transition>
          </RouterView>
        </div>
      </main>

      <footer class="border-t border-dark-200 dark:border-dark-700 bg-white dark:bg-dark-900">
        <div class="px-6 py-4 text-center text-sm text-dark-500 dark:text-dark-400">
          Fork Panel XC &mdash; Forked from XC_VM by Vateron Media &middot; AGPL-3.0
        </div>
      </footer>
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
