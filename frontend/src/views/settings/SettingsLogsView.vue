<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import settingsService from '@services/settings'
import { formatDateTime } from '@utils/format'

const uiStore = useUiStore()
const logs = ref([])
const loading = ref(true)
const level = ref('')

async function load() {
  loading.value = true
  try {
    const res = await settingsService.logs({ level: level.value || undefined, limit: 100 })
    logs.value = res.data || []
  } catch (error) {
    uiStore.toastError('Failed to load logs')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="card">
    <div class="card-header flex items-center justify-between gap-4">
      <h3 class="font-semibold text-dark-900 dark:text-white">Panel logs</h3>
      <div class="flex items-center gap-2">
        <select v-model="level" @change="load" class="input w-auto py-1.5 text-sm">
          <option value="">All levels</option>
          <option value="ERROR">Error</option>
          <option value="WARNING">Warning</option>
          <option value="NOTICE">Notice</option>
          <option value="INFO">Info</option>
        </select>
        <button @click="load" :disabled="loading" class="btn-secondary btn-sm">Refresh</button>
      </div>
    </div>
    <div class="table-container border-0 rounded-none">
      <table class="table">
        <thead><tr><th class="w-48">Time</th><th class="w-28">Level</th><th>Message</th></tr></thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td class="text-dark-500 dark:text-dark-400 whitespace-nowrap">{{ formatDateTime(log.time) }}</td>
            <td>
              <span class="badge" :class="log.type === 'ERROR' || log.type === 'FATAL' ? 'badge-danger' : log.type === 'WARNING' ? 'badge-warning' : 'badge-info'">{{ log.type }}</span>
            </td>
            <td>
              <p class="text-dark-900 dark:text-white">{{ log.log_message }}</p>
              <p class="text-xs text-dark-500 dark:text-dark-400">{{ log.file }}:{{ log.line }}</p>
            </td>
          </tr>
          <tr v-if="!loading && logs.length === 0"><td colspan="3" class="text-center py-10 text-dark-400">No log entries</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
