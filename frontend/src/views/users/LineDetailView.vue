<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import api from '@services/api'
import { formatDateTime } from '@utils/format'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()
const line = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get(`/lines/${route.params.id}`)
    line.value = data.data || data
  } catch (error) {
    uiStore.toastError('Failed to load line')
    router.push({ name: 'lines' })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else-if="line" class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Line #{{ line.id }}</h1>
      <router-link to="/lines" class="btn-secondary">Back</router-link>
    </div>

    <div class="card">
      <div class="card-body grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div><p class="text-dark-500 dark:text-dark-400">Username</p><p class="font-medium text-dark-900 dark:text-white">{{ line.username }}</p></div>
        <div><p class="text-dark-500 dark:text-dark-400">Status</p><span class="badge" :class="line.enabled ? 'badge-success' : 'badge-danger'">{{ line.enabled ? 'Active' : 'Disabled' }}</span></div>
        <div><p class="text-dark-500 dark:text-dark-400">Expires</p><p class="text-dark-900 dark:text-white">{{ formatDateTime(line.exp_date) || 'Never' }}</p></div>
        <div><p class="text-dark-500 dark:text-dark-400">Max connections</p><p class="text-dark-900 dark:text-white">{{ line.max_connections ?? 1 }}</p></div>
      </div>
    </div>
  </div>
</template>
