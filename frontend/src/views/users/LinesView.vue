<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import api from '@services/api'
import DataTable from '@components/ui/DataTable.vue'

const router = useRouter()
const uiStore = useUiStore()

const lines = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)

const columns = [
  { key: 'id', label: 'ID', width: '80px' },
  { key: 'username', label: 'Username' },
  { key: 'exp_date', label: 'Expires', width: '160px' },
  { key: 'max_connections', label: 'Connections', width: '130px' },
  { key: 'status', label: 'Status', width: '110px' },
]

async function fetchLines() {
  loading.value = true
  try {
    const { data } = await api.get('/lines', { params: { page: page.value, per_page: perPage.value } })
    lines.value = data.data || []
    total.value = data.total || 0
  } catch (error) {
    uiStore.toastError('Failed to load lines')
  } finally {
    loading.value = false
  }
}

function handlePageChange(p) { page.value = p; fetchLines() }
function handlePerPageChange(n) { perPage.value = n; page.value = 1; fetchLines() }

onMounted(fetchLines)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Lines</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Streaming lines and their connection limits</p>
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
        <template #cell-status="{ row }">
          <span class="badge" :class="row.enabled ? 'badge-success' : 'badge-danger'">{{ row.enabled ? 'Active' : 'Disabled' }}</span>
        </template>
      </DataTable>
    </div>
  </div>
</template>
