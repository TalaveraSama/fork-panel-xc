<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useUiStore } from '@stores/ui'
import streamsService from '@services/streams'
import { formatNumber, formatDateTime, formatBytes } from '@utils/format'
import { ArrowPathIcon, PlayIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const uiStore = useUiStore()

const stream = ref(null)
const stats = ref(null)
const logs = ref([])
const categories = ref([])
const loading = ref(true)
const statsLoading = ref(false)
const logsLoading = ref(false)
const savingSettings = ref(false)
const testing = ref(false)
const testResult = ref(null)
const activeTab = ref('overview')

const settings = reactive({
  stream_display_name: '',
  stream_source: '',
  stream_icon: '',
  stream_type: 'live',
  category_id: '',
  epg_channel_id: '',
  enabled: true,
})

function applyStream(data) {
  stream.value = data
  Object.assign(settings, {
    stream_display_name: data.stream_display_name || '',
    stream_source: data.stream_source || '',
    stream_icon: data.stream_icon || '',
    stream_type: data.stream_type || 'live',
    category_id: data.category_id || '',
    epg_channel_id: data.epg_channel_id || '',
    enabled: !!data.enabled,
  })
}

async function loadStats() {
  statsLoading.value = true
  try {
    const response = await streamsService.getStats(route.params.id)
    stats.value = response.data || response
  } catch (error) {
    // stats are informational
  } finally {
    statsLoading.value = false
  }
}

async function loadLogs() {
  logsLoading.value = true
  try {
    const response = await streamsService.getLogs(route.params.id, { limit: 100 })
    logs.value = response.data || []
  } catch (error) {
    // logs are informational
  } finally {
    logsLoading.value = false
  }
}

async function fetchStream() {
  loading.value = true
  try {
    const [streamRes, categoryRes] = await Promise.all([
      streamsService.get(route.params.id),
      streamsService.getCategories().catch(() => ({ data: [] })),
    ])
    applyStream(streamRes.data || streamRes)
    categories.value = categoryRes.data || []
    await Promise.all([loadStats(), loadLogs()])
  } catch (error) {
    uiStore.toastError('Failed to load stream')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  savingSettings.value = true
  try {
    const response = await streamsService.update(route.params.id, {
      stream_display_name: settings.stream_display_name,
      stream_source: settings.stream_source,
      stream_icon: settings.stream_icon,
      stream_type: settings.stream_type,
      category_id: settings.category_id || null,
      epg_channel_id: settings.epg_channel_id,
      enabled: settings.enabled,
    })
    applyStream(response.data || response)
    uiStore.toastSuccess('Stream settings saved')
    await Promise.all([loadStats(), loadLogs()])
  } catch (error) {
    uiStore.toastError(error.response?.data?.error || 'Failed to save stream settings')
  } finally {
    savingSettings.value = false
  }
}

async function runTest() {
  testing.value = true
  testResult.value = null
  try {
    const response = await streamsService.test(route.params.id)
    testResult.value = response.data || response
    uiStore.toastSuccess(testResult.value.success ? 'Stream test passed' : 'Stream test failed')
    await Promise.all([loadStats(), loadLogs()])
  } catch (error) {
    uiStore.toastError(error.response?.data?.error || 'Stream test failed')
  } finally {
    testing.value = false
  }
}

function levelClass(level) {
  if (level === 'error') return 'badge-danger'
  if (level === 'warning') return 'badge-warning'
  return 'badge-info'
}

function toggleEnabled() {
  settings.enabled = !settings.enabled
  saveSettings()
}

onMounted(fetchStream)
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64">
    <svg class="animate-spin h-8 w-8 text-primary-600" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
  </div>

  <div v-else-if="stream" class="space-y-6">
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
        <button class="btn-secondary" :disabled="testing" @click="runTest">
          <PlayIcon class="w-4 h-4" />
          {{ testing ? 'Testing…' : 'Test stream' }}
        </button>
        <router-link :to="{ name: 'stream-edit', params: { id: stream.id } }" class="btn-secondary">Full edit</router-link>
      </div>
    </div>

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
          <span v-if="tab === 'logs' && logs.length" class="ml-1 text-xs text-dark-400">({{ logs.length }})</span>
        </button>
      </nav>
    </div>

    <div class="card">
      <div class="card-body">
        <!-- Overview -->
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
                <img :src="stream.stream_icon || stream.icon" class="w-10 h-10 rounded" alt="" />
                <code class="text-sm break-all">{{ stream.stream_icon || stream.icon }}</code>
              </div>
              <p v-else class="text-dark-500 dark:text-dark-400">No icon</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="font-semibold text-dark-900 dark:text-white mb-3">Stream details</h3>
              <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-dark-500 dark:text-dark-400">Created</dt><dd class="font-medium text-dark-900 dark:text-white">{{ formatDateTime(stream.created_at) }}</dd></div>
                <div class="flex justify-between"><dt class="text-dark-500 dark:text-dark-400">Updated</dt><dd class="font-medium text-dark-900 dark:text-white">{{ formatDateTime(stream.updated_at) }}</dd></div>
                <div class="flex justify-between"><dt class="text-dark-500 dark:text-dark-400">EPG channel</dt><dd class="font-medium text-dark-900 dark:text-white">{{ stream.epg_channel_id || '—' }}</dd></div>
              </dl>
            </div>
            <div>
              <h3 class="font-semibold text-dark-900 dark:text-white mb-3">Quick stats</h3>
              <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-dark-500 dark:text-dark-400">Viewers</dt><dd class="font-medium text-dark-900 dark:text-white">{{ formatNumber(stats?.current_viewers || 0) }}</dd></div>
                <div class="flex justify-between"><dt class="text-dark-500 dark:text-dark-400">Connections</dt><dd class="font-medium text-dark-900 dark:text-white">{{ formatNumber(stats?.total_connections || 0) }}</dd></div>
                <div class="flex justify-between"><dt class="text-dark-500 dark:text-dark-400">Bandwidth</dt><dd class="font-medium text-dark-900 dark:text-white">{{ formatBytes(stats?.bandwidth || 0) }}</dd></div>
              </dl>
            </div>
          </div>

          <div v-if="testResult" class="mt-6 rounded-lg border p-4 text-sm" :class="testResult.success ? 'border-green-300 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800'">
            <p class="flex items-center gap-2 font-medium" :class="testResult.success ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
              <CheckCircleIcon v-if="testResult.success" class="w-5 h-5" />
              <XCircleIcon v-else class="w-5 h-5" />
              {{ testResult.success ? 'Source reachable' : 'Source failed' }}
            </p>
            <p class="mt-1 text-dark-600 dark:text-dark-300">
              HTTP {{ testResult.info?.http_code ?? '—' }} · {{ testResult.latency }} ms · {{ testResult.info?.content_type || 'unknown type' }}
            </p>
            <p v-if="testResult.error" class="text-red-600 dark:text-red-400">{{ testResult.error }}</p>
          </div>
        </div>

        <!-- Stats -->
        <div v-else-if="activeTab === 'stats'" class="space-y-6">
          <div class="flex justify-end">
            <button class="btn-secondary btn-sm" :disabled="statsLoading" @click="loadStats">
              <ArrowPathIcon class="w-4 h-4" :class="statsLoading ? 'animate-spin' : ''" />
              Refresh
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-4">
              <p class="text-sm text-dark-500 dark:text-dark-400">Current viewers</p>
              <p class="text-3xl font-bold text-dark-900 dark:text-white mt-1">{{ formatNumber(stats?.current_viewers || 0) }}</p>
            </div>
            <div class="card p-4">
              <p class="text-sm text-dark-500 dark:text-dark-400">Total connections</p>
              <p class="text-3xl font-bold text-dark-900 dark:text-white mt-1">{{ formatNumber(stats?.total_connections || 0) }}</p>
            </div>
            <div class="card p-4">
              <p class="text-sm text-dark-500 dark:text-dark-400">Bandwidth</p>
              <p class="text-3xl font-bold text-dark-900 dark:text-white mt-1">{{ formatBytes(stats?.bandwidth || 0) }}</p>
            </div>
            <div class="card p-4">
              <p class="text-sm text-dark-500 dark:text-dark-400">Stream status</p>
              <p class="mt-2"><span class="badge" :class="stats?.status === 'online' ? 'badge-success' : 'badge-warning'">{{ stats?.status || stream.status || 'offline' }}</span></p>
              <p class="text-xs text-dark-500 dark:text-dark-400 mt-2">Enabled: {{ stats?.enabled ? 'yes' : 'no' }}</p>
            </div>
          </div>

          <div class="rounded-lg border border-dark-200 dark:border-dark-700 p-4 text-sm">
            <p class="font-medium text-dark-900 dark:text-white mb-2">Last source test</p>
            <template v-if="stats?.last_test">
              <p class="text-dark-600 dark:text-dark-300">
                <span class="badge" :class="levelClass(stats.last_test.level)">{{ stats.last_test.level }}</span>
                {{ stats.last_test.message }} · {{ formatDateTime(stats.last_test.created_at) }}
              </p>
              <pre class="mt-2 text-xs bg-dark-50 dark:bg-dark-950 rounded p-2 overflow-x-auto">{{ JSON.stringify(stats.last_test.context, null, 2) }}</pre>
            </template>
            <p v-else class="text-dark-500 dark:text-dark-400">No tests yet. Use “Test stream”.</p>
          </div>

          <p class="text-xs text-dark-500 dark:text-dark-400">
            These counters come from <code>stream_stats</code>; they stay at 0 until a streaming/monitoring worker updates them.
          </p>
        </div>

        <!-- Settings -->
        <div v-else-if="activeTab === 'settings'">
          <form class="space-y-5" @submit.prevent="saveSettings">
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="label" for="s_name">Name</label>
                <input id="s_name" v-model="settings.stream_display_name" class="input" required />
              </div>
              <div>
                <label class="label" for="s_type">Type</label>
                <select id="s_type" v-model="settings.stream_type" class="input">
                  <option value="live">Live TV</option>
                  <option value="movie">VOD (Movie)</option>
                  <option value="series">Series</option>
                  <option value="radio">Radio</option>
                </select>
              </div>
            </div>

            <div>
              <label class="label" for="s_source">Source URL</label>
              <input id="s_source" v-model="settings.stream_source" type="url" class="input" required />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="label" for="s_icon">Icon URL</label>
                <input id="s_icon" v-model="settings.stream_icon" type="url" class="input" />
              </div>
              <div>
                <label class="label" for="s_category">Category</label>
                <select id="s_category" v-model="settings.category_id" class="input">
                  <option value="">Uncategorized</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
              </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="label" for="s_epg">EPG channel ID</label>
                <input id="s_epg" v-model="settings.epg_channel_id" class="input" placeholder="e.g. galaxya.tv" />
              </div>
              <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300 sm:mt-7">
                <input v-model="settings.enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
                Enabled
              </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-dark-200 dark:border-dark-700 pt-4">
              <div class="flex gap-2">
                <button type="button" class="btn-secondary" :disabled="testing" @click="runTest">
                  <PlayIcon class="w-4 h-4" />
                  {{ testing ? 'Testing…' : 'Test source' }}
                </button>
                <router-link :to="{ name: 'stream-edit', params: { id: stream.id } }" class="btn-ghost">Full edit</router-link>
              </div>
              <button type="submit" class="btn-primary" :disabled="savingSettings">
                {{ savingSettings ? 'Saving…' : 'Save settings' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Logs -->
        <div v-else-if="activeTab === 'logs'" class="space-y-4">
          <div class="flex justify-end">
            <button class="btn-secondary btn-sm" :disabled="logsLoading" @click="loadLogs">
              <ArrowPathIcon class="w-4 h-4" :class="logsLoading ? 'animate-spin' : ''" />
              Refresh
            </button>
          </div>

          <div v-if="logsLoading" class="text-sm text-dark-400">Loading…</div>
          <div v-else-if="logs.length === 0" class="text-sm text-dark-500 dark:text-dark-400">
            No events yet. Creating/updating the stream or running a test writes here.
          </div>
          <ul v-else class="divide-y divide-dark-100 dark:divide-dark-800">
            <li v-for="entry in logs" :key="entry.id" class="py-3 text-sm">
              <div class="flex flex-wrap items-center gap-2">
                <span class="badge" :class="levelClass(entry.level)">{{ entry.level }}</span>
                <span class="font-medium text-dark-900 dark:text-white">{{ entry.message }}</span>
                <span class="text-xs text-dark-500 dark:text-dark-400 ml-auto">{{ formatDateTime(entry.created_at) }}</span>
              </div>
              <pre v-if="entry.context && Object.keys(entry.context).length" class="mt-1 text-xs bg-dark-50 dark:bg-dark-950 rounded p-2 overflow-x-auto">{{ JSON.stringify(entry.context, null, 2) }}</pre>
            </li>
          </ul>
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
