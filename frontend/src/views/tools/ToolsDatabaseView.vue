<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import toolsService from '@services/tools'

const uiStore = useUiStore()
const data = ref(null)
const loading = ref(false)
const running = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await toolsService.database()
    data.value = res.data || res
  } catch (error) {
    uiStore.toastError('Failed to load database status')
  } finally {
    loading.value = false
  }
}

async function migrate() {
  if (!window.confirm('Apply pending database migrations?')) return
  running.value = true
  try {
    await toolsService.runMigrations()
    uiStore.toastSuccess('Migrations applied')
    await load()
  } catch (error) {
    uiStore.toastError('Migration failed')
  } finally {
    running.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="card">
      <div class="card-header flex items-center justify-between">
        <h3 class="font-semibold text-dark-900 dark:text-white">Database</h3>
        <button @click="load" :disabled="loading" class="btn-secondary btn-sm">Refresh</button>
      </div>
      <div class="card-body">
        <div v-if="loading" class="text-dark-400 text-sm">Loading...</div>
        <dl v-else-if="data" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div v-for="(value, key) in data" :key="key" class="flex justify-between gap-4 border-b border-dark-100 dark:border-dark-800 pb-2">
            <dt class="text-dark-500 dark:text-dark-400 capitalize">{{ String(key).replaceAll('_', ' ') }}</dt>
            <dd class="font-medium text-dark-900 dark:text-white text-right">{{ value }}</dd>
          </div>
        </dl>
      </div>
      <div class="card-footer">
        <button @click="migrate" :disabled="running" class="btn-primary">{{ running ? 'Applying…' : 'Apply pending migrations' }}</button>
      </div>
    </div>
  </div>
</template>
