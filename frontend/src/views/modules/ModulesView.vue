<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import modulesService from '@services/modules'

const uiStore = useUiStore()
const installed = ref([])
const available = ref([])
const loading = ref(false)
const busy = ref(null)

async function load() {
  loading.value = true
  try {
    const res = await modulesService.list()
    installed.value = res.installed || []
    available.value = res.available || []
  } catch (error) {
    uiStore.toastError('Failed to load modules')
  } finally {
    loading.value = false
  }
}

async function action(name, moduleId) {
  busy.value = moduleId
  try {
    await modulesService[name](moduleId)
    uiStore.toastSuccess(`Module ${name} completed`)
    await load()
  } catch (error) {
    uiStore.toastError(`Failed to ${name} module`)
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Modules</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Extend the panel with installable modules</p>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Installed</h3></div>
      <div class="card-body">
        <div v-if="loading" class="text-sm text-dark-400">Loading...</div>
        <div v-else-if="installed.length === 0" class="text-sm text-dark-500 dark:text-dark-400">
          No modules installed yet.
        </div>
        <ul v-else class="divide-y divide-dark-100 dark:divide-dark-800">
          <li v-for="module in installed" :key="module.id" class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <p class="font-medium text-dark-900 dark:text-white">{{ module.name }} <span class="text-xs text-dark-400">v{{ module.version }}</span></p>
              <p class="text-sm text-dark-500 dark:text-dark-400">{{ module.description }}</p>
            </div>
            <div class="flex gap-2">
              <span class="badge" :class="module.status === 'enabled' ? 'badge-success' : 'badge-warning'">{{ module.status }}</span>
              <button v-if="module.status === 'enabled'" @click="action('disable', module.id)" :disabled="busy === module.id" class="btn-secondary btn-sm">Disable</button>
              <button v-else @click="action('enable', module.id)" :disabled="busy === module.id" class="btn-primary btn-sm">Enable</button>
              <router-link :to="{ name: 'module-detail', params: { id: module.id } }" class="btn-secondary btn-sm">Details</router-link>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Available</h3></div>
      <div class="card-body">
        <div v-if="available.length === 0" class="text-sm text-dark-500 dark:text-dark-400">No additional modules are bundled with this build.</div>
        <ul v-else class="divide-y divide-dark-100 dark:divide-dark-800">
          <li v-for="module in available" :key="module.id" class="py-4 flex items-center justify-between gap-3">
            <div>
              <p class="font-medium text-dark-900 dark:text-white">{{ module.name }}</p>
              <p class="text-sm text-dark-500 dark:text-dark-400">{{ module.description }}</p>
            </div>
            <button @click="action('install', module.id)" :disabled="busy === module.id" class="btn-primary btn-sm">Install</button>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
