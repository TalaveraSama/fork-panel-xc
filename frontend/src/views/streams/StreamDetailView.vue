<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useUiStore } from '@stores/ui'
import streamsService from '@services/streams'
import { formatNumber, formatDateTime, formatBytes } from '@utils/format'

const route = useRoute()
const uiStore = useUiStore()

const stream = ref(null)
const loading = ref(true)
const stats = ref(null)
const activeTab = ref('overview')

async function fetchStream() {
  loading.value = true
  try {
    const [streamRes, statsRes] = await Promise.all([
      streamsService.get(route.params.id),
      streamsService.getStats(route.params.id),
    ])
    stream.value = streamRes.data || streamRes
    stats.value = statsRes.data || statsRes
  } catch (error) {
    uiStore.toastError('Failed to load stream')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchStream()
})
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64">
    <svg class="animate-spin h-8 w-8 text-primary-600" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
  </div>

  <div v-else-if="stream" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">{{ stream.stream_display_name || stream.name }}</h1>
        <div class="flex items-center gap-4 mt-2 text-sm text-dark-500 dark:text-dark-400">
          <span class="flex items-center gap-1">
            <span :class="['w-2 h-2 rounded-full', stream.enabled ? 'bg-green-500' : 'bg-red-500']" />
            {{ stream.enabled ? 'Enabled' : 'Disabled' }}
          </span>
          <span>ID: {{ stream.id }}</span>
          <span>Type: {{ stream.stream_type || stream.type }}</span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <router-link :to="{ name: 'stream-edit', params: { id: stream.id } }" class="btn-secondary">Edit</router-link>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-dark-200 dark:border-dark-700">
      <nav class="flex gap-4" aria-label="Stream tabs">
        <button
          v-for="tab in ['overview', 'stats', 'settings', 'logs']"
          :key="tab"
          @click="activeTab = tab"
          :class="[
            'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
            activeTab === tab
              ? 'border-primary-600 text-primary-600 dark:text-primary-400'
              : 'border-transparent text-dark-500 hover:text-dark-700 dark:text-dark-400 dark:hover:text-dark-200',
          ]"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
        </button>
      </nav>
    </div>

    <!-- Tab Content -->
    <div class="card">
      <div class="card-body">
        <!-- Overview Tab -->
        <div v-if="activeTab === 'overview'">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="card p-4">
              <p class="text-sm text-dark-500 dark:text-dark-400">Source URL</p>
              <code class="text-sm text-dark-900 dark:text-white break-all">{{ stream.stream_source || stream.source }}</code>
            </div>
            <div class="card p-4">
              <p class="text-sm text-dark-500 dark:text-dark-400">Category</p>
              <p class="font-medium text-dark-900 dark:text-white">{{ stream.category_name || stream.category || '-' }}</p>
            </div>
            <div class="card p-4">
              <p class="text-sm text-dark-500 dark:text-dark-400">Icon</p>
              <div v-if="stream.stream_icon || stream.icon" class="flex items-center gap-2">
                <img :src="stream.stream_icon || stream.icon" class="w-10 h-10 rounded" />
                <code class="text-sm">{{ stream.stream_icon || stream.icon }}</code>
              </div>
              <p v-else class="text-dark-500 dark:text-dark-400">No icon</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="font-semibold text-dark-900 dark:text-white mb-3">Stream Details</h3>
              <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <dt class="text-dark-500 dark:text-dark-400">Created</dt>
                  <dd class="font-medium text-dark-900 dark:text-white">{{ formatDateTime(stream.created_at) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-dark-500 dark:text-dark-400">Updated</dt>
                  <dd class="font-medium text-dark-900 dark:text-white">{{ formatDateTime(stream.updated_at) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-dark-500 dark:text-dark-400">Stream ID</dt>
                  <dd class="font-medium text-dark-900 dark:text-white">{{ stream.stream_id || stream.id }}</dd>
                </div>
              </dl>
            </div>
            <div>
              <h3 class="font-semibold text-dark-900 dark:text-white mb-3">Statistics</h3>
              <dl class="space-y-2 text-sm" v-if="stats">
                <div class="flex justify-between">
                  <dt class="text-dark-500 dark:text-dark-400">Current Viewers</dt>
                  <dd class="font-medium text-dark-900 dark:text-white">{{ stats.current_viewers || 0 }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-dark-500 dark:text-dark-400">Total Connections</dt>
                  <dd class="font-medium text-dark-900 dark:text-white">{{ formatNumber(stats.total_connections || 0) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-dark-500 dark:text-dark-400">Bandwidth Used</dt>
                  <dd class="font-medium text-dark-900 dark:text-white">{{ formatBytes(stats.bandwidth || 0) }}</dd>
                </div>
              </dl>
              <p v-else class="text-dark-500 dark:text-dark-400">No statistics available</p>
            </div>
          </div>
        </div>

        <!-- Stats Tab -->
        <div v-else-if="activeTab === 'stats'">
          <p class="text-dark-500 dark:text-dark-400">Detailed statistics coming soon...</p>
        </div>

        <!-- Settings Tab -->
        <div v-else-if="activeTab === 'settings'">
          <p class="text-dark-500 dark:text-dark-400">Stream settings coming soon...</p>
        </div>

        <!-- Logs Tab -->
        <div v-else-if="activeTab === 'logs'">
          <p class="text-dark-500 dark:text-dark-400">Stream logs coming soon...</p>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="card">
    <div class="card-body text-center py-12">
      <p class="text-dark-500 dark:text-dark-400">Stream not found</p>
    </div>
  </div>
</template>

<script>
import { formatNumber, formatDateTime, formatBytes } from '@utils/format'

export default {
  name: 'StreamDetailView',
}
</script>