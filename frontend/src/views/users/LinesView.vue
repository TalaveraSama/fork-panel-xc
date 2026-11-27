<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import linesService from '@services/lines'
import DataTable from '@components/ui/DataTable.vue'
import { formatExpiryDate } from '@utils/format'
import { PlusIcon, MagnifyingGlassIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()

const lines = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const search = ref('')
const status = ref('')

const columns = [
  { key: 'id', label: 'ID', width: '70px' },
  { key: 'username', label: 'Username' },
  { key: 'owner', label: 'Owner', width: '150px' },
  { key: 'exp_date', label: 'Expires', width: '150px' },
  { key: 'max_connections', label: 'Conns', width: '80px' },
  { key: 'status', label: 'Status', width: '110px' },
  { key: 'actions', label: '', width: '90px' },
]

async function fetchLines() {
  loading.value = true
  try {
    const response = await linesService.list({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
      status: status.value || undefined,
    })
    lines.value = response.data || []
    total.value = response.total || 0
  } catch (error) {
    uiStore.toastError('Failed to load lines')
  } finally {
    loading.value = false
  }
}

function handlePageChange(p) { page.value = p; fetchLines() }
function handlePerPageChange(n) { perPage.value = n; page.value = 1; fetchLines() }

function badgeFor(line) {
  if (line.status === 'active') return 'badge-success'
  if (line.status === 'expired') return 'badge-warning'
  return 'badge-danger'
}

async function removeLine(id) {
  if (!window.confirm('Delete this line? Subscribers using it will stop working.')) return

  try {
    await linesService.delete(id)
    uiStore.toastSuccess('Line deleted')
    fetchLines()
  } catch (error) {
    uiStore.toastError('Failed to delete line')
  }
}

onMounted(fetchLines)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Lines</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Streaming lines (subscriptions), expiration and connection limits</p>
      </div>
      <router-link to="/lines/create" class="btn-primary">
        <PlusIcon class="w-5 h-5" />
        Create Line
      </router-link>
    </div>

    <div class="card">
      <div class="card-body px-4 py-4">
        <div class="flex flex-col lg:flex-row gap-3">
          <div class="relative flex-1 max-w-md">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-dark-400" />
            <input v-model="search" @keyup.enter="fetchLines" type="text" class="input pl-10" placeholder="Search lines…" />
          </div>
          <select v-model="status" @change="fetchLines" class="input w-auto">
            <option value="">All status</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="disabled">Disabled</option>
          </select>
          <button @click="fetchLines" :disabled="loading" class="btn-secondary">
            <ArrowPathIcon class="w-4 h-4" :class="loading ? 'animate-spin' : ''" />
            Refresh
          </button>
        </div>
      </div>
    </div>

    <div class="card">
      <DataTable
        :columns="columns"
        :data="lines"
        :loading="loading"
        :total="total"
        :page="page"
        :per-page="perPage"
        :selectable="false"
        @page-change="handlePageChange"
        @per-page-change="handlePerPageChange"
        @row-click="$event => router.push({ name: 'line-detail', params: { id: $event.id } })"
        row-clickable
      >
        <template #cell-username="{ row }">
          <span class="font-medium text-dark-900 dark:text-white">{{ row.username }}</span>
        </template>
        <template #cell-owner="{ row }">
          <span class="text-sm text-dark-600 dark:text-dark-300">{{ row.owner || '—' }}</span>
        </template>
        <template #cell-exp_date="{ row }">
          <span class="text-sm text-dark-600 dark:text-dark-300">{{ formatExpiryDate(row.exp_date) }}</span>
        </template>
        <template #cell-max_connections="{ row }">{{ row.max_connections }}</template>
        <template #cell-status="{ row }">
          <span class="badge" :class="badgeFor(row)">{{ row.status }}</span>
        </template>
        <template #cell-actions="{ row }">
          <div class="flex items-center gap-1">
            <router-link :to="{ name: 'line-edit', params: { id: row.id } }" class="p-1.5 rounded-lg text-dark-500 hover:text-dark-900 dark:text-dark-400 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800" title="Edit">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </router-link>
            <button @click.stop="removeLine(row.id)" class="p-1.5 rounded-lg text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20" title="Delete">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </template>
      </DataTable>
    </div>
  </div>
</template>
