<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import seriesService from '@services/series'
import DataTable from '@components/ui/DataTable.vue'
import { PlusIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()

const series = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const search = ref('')
const sort = ref({ field: 'id', direction: 'desc' })

const columns = [
  { key: 'id', label: 'ID', width: '80px', sortable: true },
  { key: 'name', label: 'Title', sortable: true },
  { key: 'seasons', label: 'Seasons', width: '110px' },
  { key: 'episodes', label: 'Episodes', width: '110px' },
  { key: 'status', label: 'Status', width: '110px' },
]

async function fetchSeries() {
  loading.value = true
  try {
    const res = await seriesService.list({ page: page.value, per_page: perPage.value, search: search.value || undefined })
    series.value = res.data || []
    total.value = res.total || 0
  } catch (error) {
    uiStore.toastError('Failed to load series')
  } finally {
    loading.value = false
  }
}

function handleSort(field) {
  sort.value = sort.value.field === field
    ? { field, direction: sort.value.direction === 'asc' ? 'desc' : 'asc' }
    : { field, direction: 'asc' }
  fetchSeries()
}

function handlePageChange(p) { page.value = p; fetchSeries() }
function handlePerPageChange(n) { perPage.value = n; page.value = 1; fetchSeries() }

onMounted(fetchSeries)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Series</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Manage TV series and episodes</p>
    </div>

    <router-link to="/series/create" class="btn-primary w-fit">
      <PlusIcon class="w-5 h-5" />
      Add Series
    </router-link>

    <div class="card">
      <div class="card-body px-4 py-4">
        <input v-model="search" @keyup.enter="fetchSeries" type="text" class="input max-w-md" placeholder="Search series..." />
      </div>
    </div>

    <div class="card">
      <DataTable
        :columns="columns"
        :data="series"
        :loading="loading"
        :total="total"
        :page="page"
        :per-page="perPage"
        :sort="sort"
        :selectable="false"
        @sort="handleSort"
        @page-change="handlePageChange"
        @per-page-change="handlePerPageChange"
        @row-click="$event => router.push({ name: 'series-detail', params: { id: $event.id } })"
        row-clickable
      >
        <template #cell-name="{ row }">
          <span class="font-medium text-dark-900 dark:text-white">{{ row.name || row.title }}</span>
        </template>
        <template #cell-seasons="{ row }">{{ row.seasons_count ?? 0 }}</template>
        <template #cell-episodes="{ row }">{{ row.episodes_count ?? 0 }}</template>
        <template #cell-status="{ row }">
          <span class="badge" :class="row.enabled ? 'badge-success' : 'badge-danger'">{{ row.enabled ? 'Enabled' : 'Disabled' }}</span>
        </template>
      </DataTable>
    </div>
  </div>
</template>
