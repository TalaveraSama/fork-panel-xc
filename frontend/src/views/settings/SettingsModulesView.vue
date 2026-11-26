<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import modulesService from '@services/modules'

const uiStore = useUiStore()
const modules = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await modulesService.list()
    modules.value = res.installed || []
  } catch (error) {
    uiStore.toastError('Failed to load modules')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="card">
    <div class="card-header flex items-center justify-between">
      <h3 class="font-semibold text-dark-900 dark:text-white">Modules</h3>
      <router-link to="/modules" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">Manage modules</router-link>
    </div>
    <div class="card-body">
      <p v-if="loading" class="text-sm text-dark-400">Loading...</p>
      <p v-else-if="modules.length === 0" class="text-sm text-dark-500 dark:text-dark-400">No modules installed.</p>
      <ul v-else class="divide-y divide-dark-100 dark:divide-dark-800 text-sm">
        <li v-for="module in modules" :key="module.id" class="py-3 flex items-center justify-between gap-3">
          <div>
            <p class="font-medium text-dark-900 dark:text-white">{{ module.name }} <span class="text-xs text-dark-400">v{{ module.version }}</span></p>
            <p class="text-dark-500 dark:text-dark-400">{{ module.description }}</p>
          </div>
          <span class="badge" :class="module.status === 'enabled' ? 'badge-success' : 'badge-warning'">{{ module.status }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
