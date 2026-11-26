<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import settingsService from '@services/settings'
import { formatBytes, formatDateTime } from '@utils/format'

const uiStore = useUiStore()
const backups = ref([])
const loading = ref(true)
const creating = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await settingsService.backup()
    backups.value = res.data || res.backups || []
  } catch (error) {
    uiStore.toastError('Failed to load backups')
  } finally {
    loading.value = false
  }
}

async function createBackup() {
  creating.value = true
  try {
    await settingsService.createBackup()
    uiStore.toastSuccess('Backup started')
    await load()
  } catch (error) {
    uiStore.toastError('Failed to start backup')
  } finally {
    creating.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="card">
    <div class="card-header flex items-center justify-between">
      <h3 class="font-semibold text-dark-900 dark:text-white">Backups</h3>
      <button @click="createBackup" :disabled="creating" class="btn-primary btn-sm">{{ creating ? 'Starting…' : 'Create backup' }}</button>
    </div>
    <div class="table-container border-0 rounded-none">
      <table class="table">
        <thead><tr><th>File</th><th class="w-32">Size</th><th class="w-48">Created</th></tr></thead>
        <tbody>
          <tr v-for="backup in backups" :key="backup.id || backup.name">
            <td class="font-medium text-dark-900 dark:text-white">{{ backup.name || backup.id }}</td>
            <td>{{ formatBytes(backup.size || 0) }}</td>
            <td class="text-dark-500 dark:text-dark-400">{{ formatDateTime(backup.created_at) }}</td>
          </tr>
          <tr v-if="!loading && backups.length === 0"><td colspan="3" class="text-center py-10 text-dark-400">No backups yet</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
