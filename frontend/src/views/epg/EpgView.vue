<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import epgService from '@services/epg'

const uiStore = useUiStore()
const overview = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await epgService.overview()
    overview.value = res.data || res
  } catch (error) {
    uiStore.toastError('Failed to load EPG overview')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">EPG</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Electronic program guide sources and channels</p>
      </div>
      <div class="flex gap-2">
        <router-link to="/epg/sources" class="btn-secondary">Sources</router-link>
        <router-link to="/epg/channels" class="btn-primary">Channels</router-link>
      </div>
    </div>

    <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="card"><div class="card-body">
        <p class="text-sm text-dark-500 dark:text-dark-400">Sources</p>
        <p class="text-3xl font-bold text-dark-900 dark:text-white mt-1">{{ overview?.sources ?? 0 }}</p>
      </div></div>
      <div class="card"><div class="card-body">
        <p class="text-sm text-dark-500 dark:text-dark-400">Channels</p>
        <p class="text-3xl font-bold text-dark-900 dark:text-white mt-1">{{ overview?.channels ?? 0 }}</p>
      </div></div>
      <div class="card"><div class="card-body">
        <p class="text-sm text-dark-500 dark:text-dark-400">Last sync</p>
        <p class="text-xl font-semibold text-dark-900 dark:text-white mt-2">{{ overview?.last_sync || 'Never' }}</p>
      </div></div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">About EPG</h3></div>
      <div class="card-body text-sm text-dark-600 dark:text-dark-400 space-y-2">
        <p>EPG sources are fetched by the <code class="bg-dark-100 dark:bg-dark-800 px-1.5 py-0.5 rounded">cron:epg</code> job and written as XMLTV files per bouquet.</p>
        <p>Add a source under <router-link to="/epg/sources" class="text-primary-600 dark:text-primary-400">Sources</router-link>, then map channels to your streams.</p>
      </div>
    </div>
  </div>
</template>
