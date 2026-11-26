<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import epgService from '@services/epg'
import DataTable from '@components/ui/DataTable.vue'

const uiStore = useUiStore()
const channels = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const search = ref('')

const columns = [
  { key: 'id', label: 'ID', width: '80px' },
  { key: 'name', label: 'Channel' },
  { key: 'epg_id', label: 'EPG ID' },
  { key: 'source', label: 'Source', width: '180px' },
]

async function fetchChannels() {
  loading.value = true
  try {
    const res = await epgService.channels({ page: page.value, per_page: perPage.value, search: search.value || undefined })
    channels.value = res.data || []
    total.value = res.total || 0
  } catch (error) {
    uiStore.toastError('Failed to load EPG channels')
  } finally {
    loading.value = false
  }
}

function handlePageChange(p) { page.value = p; fetchChannels() }
function handlePerPageChange(n) { perPage.value = n; page.value = 1; fetchChannels() }

onMounted(fetchChannels)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">EPG Channels</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Channels known from the configured sources</p>
    </div>

    <div class="card">
      <div class="card-body px-4 py-4">
        <input v-model="search" @keyup.enter="fetchChannels" type="text" class="input max-w-md" placeholder="Search channels..." />
      </div>
    </div>

    <div class="card">
      <DataTable
        :columns="columns"
        :data="channels"
        :loading="loading"
        :total="total"
        :page="page"
        :per-page="perPage"
        :selectable="false"
        @page-change="handlePageChange"
        @per-page-change="handlePerPageChange"
      />
    </div>
  </div>
</template>
