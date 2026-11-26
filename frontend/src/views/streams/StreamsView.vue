<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import streamsService from '@services/streams'
import { formatNumber, getStatusColor } from '@utils/format'
import DataTable from '@components/ui/DataTable.vue'
import { PlusIcon, MagnifyingGlassIcon, FunnelIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()

// State
const streams = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const search = ref('')
const filters = ref({
  status: '',
  category_id: '',
  type: '',
})
const sort = ref({ field: 'id', direction: 'desc' })
const selectedIds = ref([])
const categories = ref([])

// Computed
const hasSelection = computed(() => selectedIds.value.length > 0)

// Fetch data
async function fetchStreams() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
      status: filters.value.status || undefined,
      category_id: filters.value.category_id || undefined,
      type: filters.value.type || undefined,
      sort_field: sort.value.field,
      sort_direction: sort.value.direction,
    }

    const response = await streamsService.list(params)
    streams.value = response.data || []
    total.value = response.total || 0
  } catch (error) {
    uiStore.toastError('Failed to load streams')
  } finally {
    loading.value = false
  }
}

async function fetchCategories() {
  try {
    const response = await streamsService.getCategories()
    categories.value = response.data || []
  } catch (error) {
    console.error('Failed to load categories:', error)
  }
}

// Selection
function toggleSelectAll(event) {
  if (event.target.checked) {
    selectedIds.value = streams.value.map(s => s.id)
  } else {
    selectedIds.value = []
  }
}

function toggleSelect(id) {
  const index = selectedIds.value.indexOf(id)
  if (index === -1) {
    selectedIds.value.push(id)
  } else {
    selectedIds.value.splice(index, 1)
  }
}

function isSelected(id) {
  return selectedIds.value.includes(id)
}

function isAllSelected() {
  return streams.value.length > 0 && streams.value.every(s => selectedIds.value.includes(s.id))
}

// Actions
async function bulkDelete() {
  if (!hasSelection.value) return

  const confirmed = window.confirm(`Delete ${selectedIds.value.length} stream(s)? This cannot be undone.`)
  if (!confirmed) return

  try {
    await streamsService.bulkDelete(selectedIds.value)
    uiStore.toastSuccess(`${selectedIds.value.length} stream(s) deleted`)
    selectedIds.value = []
    fetchStreams()
  } catch (error) {
    uiStore.toastError('Failed to delete streams')
  }
}

async function bulkUpdate(status) {
  if (!hasSelection.value) return

  try {
    await streamsService.bulkUpdate(selectedIds.value, { enabled: status })
    uiStore.toastSuccess(`${selectedIds.value.length} stream(s) updated`)
    selectedIds.value = []
    fetchStreams()
  } catch (error) {
    uiStore.toastError('Failed to update streams')
  }
}

async function deleteStream(id) {
  const confirmed = window.confirm('Delete this stream? This cannot be undone.')
  if (!confirmed) return

  try {
    await streamsService.delete(id)
    uiStore.toastSuccess('Stream deleted')
    fetchStreams()
  } catch (error) {
    uiStore.toastError('Failed to delete stream')
  }
}

function handleSort(field) {
  if (sort.value.field === field) {
    sort.value.direction = sort.value.direction === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value.field = field
    sort.value.direction = 'asc'
  }
  fetchStreams()
}

function handlePageChange(newPage) {
  page.value = newPage
  fetchStreams()
}

function handlePerPageChange(newPerPage) {
  perPage.value = newPerPage
  page.value = 1
  fetchStreams()
}

function clearFilters() {
  filters.value = { status: '', category_id: '', type: '' }
  search.value = ''
  page.value = 1
  fetchStreams()
}

// Table columns
const columns = [
  { key: 'select', label: '', width: '40px', sortable: false },
  { key: 'id', label: 'ID', width: '80px', sortable: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'type', label: 'Type', width: '100px', sortable: true },
  { key: 'category', label: 'Category', width: '150px', sortable: true },
  { key: 'status', label: 'Status', width: '100px', sortable: true },
  { key: 'source', label: 'Source', sortable: false },
  { key: 'actions', label: 'Actions', width: '120px', sortable: false },
]

onMounted(() => {
  fetchStreams()
  fetchCategories()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Streams</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Manage live streams, VOD, and radio channels</p>
      </div>
      <router-link to="/streams/create" class="btn-primary">
        <PlusIcon class="w-5 h-5" />
        Add Stream
      </router-link>
    </div>

    <!-- Toolbar -->
    <div class="card">
      <div class="card-body px-4 py-4">
        <div class="flex flex-col lg:flex-row gap-4">
          <!-- Search -->
          <div class="relative flex-1 max-w-md">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-dark-400" />
            <input
              v-model="search"
              @keyup.enter="fetchStreams"
              type="text"
              placeholder="Search streams..."
              class="input pl-10"
            />
          </div>

          <!-- Filters -->
          <div class="flex items-center gap-3">
            <select v-model="filters.status" @change="fetchStreams" class="input w-auto">
              <option value="">All Status</option>
              <option value="enabled">Enabled</option>
              <option value="disabled">Disabled</option>
              <option value="online">Online</option>
              <option value="offline">Offline</option>
            </select>

            <select v-model="filters.category_id" @change="fetchStreams" class="input w-auto">
              <option value="">All Categories</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>

            <select v-model="filters.type" @change="fetchStreams" class="input w-auto">
              <option value="">All Types</option>
              <option value="live">Live</option>
              <option value="vod">VOD</option>
              <option value="series">Series</option>
              <option value="radio">Radio</option>
            </select>

            <button @click="clearFilters" class="btn-secondary">
              <FunnelIcon class="w-4 h-4" />
              Clear
            </button>

            <button @click="fetchStreams" :disabled="loading" class="btn-secondary">
              <ArrowPathIcon class="w-4 h-4" :class="loading ? 'animate-spin' : ''" />
              Refresh
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div v-if="hasSelection" class="card border-primary-200 dark:border-primary-800 bg-primary-50/50 dark:bg-primary-900/10">
      <div class="card-body px-4 py-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <span class="font-medium text-primary-800 dark:text-primary-200">
            {{ selectedIds.length }} stream(s) selected
          </span>
          <div class="flex items-center gap-2">
            <button @click="bulkUpdate(1)" class="btn-secondary btn-sm">Enable</button>
            <button @click="bulkUpdate(0)" class="btn-secondary btn-sm">Disable</button>
            <button @click="bulkDelete" class="btn-danger btn-sm">Delete</button>
            <button @click="selectedIds = []" class="btn-ghost btn-sm">Clear</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Data Table -->
    <div class="card">
      <DataTable
        :columns="columns"
        :data="streams"
        :loading="loading"
        :total="total"
        :page="page"
        :per-page="perPage"
        :sort="sort"
        :selected-ids="selectedIds"
        @sort="handleSort"
        @page-change="handlePageChange"
        @per-page-change="handlePerPageChange"
        @selection-change="toggleSelectAll"
        @row-click="($event) => router.push({ name: 'stream-detail', params: { id: $event.id } })"
      >
        <template #cell-select="{ row }">
          <input
            type="checkbox"
            :checked="isSelected(row.id)"
            @change="toggleSelect(row.id)"
            @click.stop
            class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500"
          />
        </template>

        <template #cell-name="{ row }">
          <div>
            <p class="font-medium text-dark-900 dark:text-white">{{ row.stream_display_name || row.name }}</p>
            <p class="text-xs text-dark-500 dark:text-dark-400 truncate max-w-xs">{{ row.stream_source || row.source }}</p>
          </div>
        </template>

        <template #cell-type="{ row }">
          <span :class="['badge', getStatusColor(row.stream_type || row.type)]">
            {{ row.stream_type || row.type || 'Live' }}
          </span>
        </template>

        <template #cell-category="{ row }">
          <span class="text-sm text-dark-700 dark:text-dark-300">{{ row.category_name || row.category || '-' }}</span>
        </template>

        <template #cell-status="{ row }">
          <span :class="['badge', getStatusColor(row.enabled ? 'online' : 'offline')]">
            {{ row.enabled ? 'Enabled' : 'Disabled' }}
          </span>
        </template>

        <template #cell-source="{ row }">
          <code class="text-xs text-dark-500 dark:text-dark-400 bg-dark-100 dark:bg-dark-800 px-2 py-1 rounded truncate block max-w-xs">{{ row.stream_source || row.source }}</code>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center gap-1">
            <router-link
              :to="{ name: 'stream-edit', params: { id: row.id } }"
              class="p-1.5 rounded-lg text-dark-500 hover:text-dark-900 dark:text-dark-400 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
              title="Edit"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </router-link>
            <button
              @click.stop="deleteStream(row.id)"
              class="p-1.5 rounded-lg text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
              title="Delete"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Empty State -->
    <div v-if="!loading && streams.length === 0" class="card">
      <div class="card-body py-16 text-center">
        <svg class="w-16 h-16 mx-auto text-dark-300 dark:text-dark-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        <h3 class="text-lg font-medium text-dark-900 dark:text-white mb-2">No streams found</h3>
        <p class="text-dark-500 dark:text-dark-400 mb-4">Get started by adding your first stream</p>
        <router-link to="/streams/create" class="btn-primary inline-flex">
          <PlusIcon class="w-5 h-5" />
          Add Stream
        </router-link>
      </div>
    </div>
  </div>
</template>