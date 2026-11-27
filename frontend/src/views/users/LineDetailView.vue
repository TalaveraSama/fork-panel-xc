<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import linesService from '@services/lines'
import { formatDateTime, formatExpiryDate } from '@utils/format'
import { ClipboardDocumentIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()

const line = ref(null)
const loading = ref(true)
const credentials = ref(null)

onMounted(async () => {
  try {
    line.value = await linesService.get(route.params.id)
  } catch (error) {
    uiStore.toastError('Failed to load line')
    router.push({ name: 'lines' })
  } finally {
    loading.value = false
  }
})

async function resetPassword() {
  if (!window.confirm('Generate a new password for this line?')) return

  try {
    const response = await linesService.resetPassword(route.params.id)
    credentials.value = response.credentials
    uiStore.toastSuccess('Password reset')
  } catch (error) {
    uiStore.toastError('Failed to reset password')
  }
}

async function copy(text) {
  try {
    await navigator.clipboard.writeText(text)
    uiStore.toastSuccess('Copied')
  } catch (error) {
    uiStore.toastError('Copy failed')
  }
}

function badgeFor(status) {
  if (status === 'active') return 'badge-success'
  if (status === 'expired') return 'badge-warning'
  return 'badge-danger'
}
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else-if="line" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Line {{ line.username }}</h1>
        <div class="flex items-center gap-2 mt-2">
          <span class="badge" :class="badgeFor(line.status)">{{ line.status }}</span>
          <span class="text-sm text-dark-500 dark:text-dark-400">#{{ line.id }}</span>
        </div>
      </div>
      <div class="flex gap-2">
        <button class="btn-secondary" @click="resetPassword">
          <ArrowPathIcon class="w-4 h-4" />
          Reset password
        </button>
        <router-link :to="{ name: 'line-edit', params: { id: line.id } }" class="btn-primary">Edit</router-link>
        <router-link to="/lines" class="btn-secondary">Back</router-link>
      </div>
    </div>

    <div v-if="credentials" class="card border-green-300 dark:border-green-800">
      <div class="card-body space-y-2">
        <p class="font-medium text-dark-900 dark:text-white">New credentials (save them now)</p>
        <div class="flex flex-wrap items-center gap-2">
          <code class="rounded-lg bg-dark-100 dark:bg-dark-800 px-3 py-2">{{ credentials.username }} / {{ credentials.password }}</code>
          <button type="button" class="btn-secondary btn-sm" @click="copy(`${credentials.username} / ${credentials.password}`)">
            <ClipboardDocumentIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Subscription</h3></div>
        <div class="card-body space-y-3 text-sm">
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Owner</span><span class="text-dark-900 dark:text-white">{{ line.owner || 'Unassigned' }}</span></div>
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Expires</span><span class="text-dark-900 dark:text-white">{{ formatExpiryDate(line.exp_date) }}</span></div>
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Max connections</span><span class="text-dark-900 dark:text-white">{{ line.max_connections }}</span></div>
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Created</span><span class="text-dark-900 dark:text-white">{{ formatDateTime(line.created_at) }}</span></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Live status</h3></div>
        <div class="card-body space-y-3 text-sm">
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Connected</span><span class="badge" :class="line.connected ? 'badge-success' : 'badge-info'">{{ line.connected ? 'Yes' : 'No' }}</span></div>
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Bitrate</span><span class="text-dark-900 dark:text-white">{{ line.bitrate || 0 }} kbps</span></div>
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Enabled</span><span class="text-dark-900 dark:text-white">{{ line.enabled ? 'Yes' : 'No' }}</span></div>
        </div>
      </div>
    </div>
  </div>
</template>
