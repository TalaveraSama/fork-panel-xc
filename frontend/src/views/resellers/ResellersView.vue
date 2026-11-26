<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import resellersService from '@services/resellers'
import DataTable from '@components/ui/DataTable.vue'
import { PlusIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()

const resellers = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)

const columns = [
  { key: 'id', label: 'ID', width: '80px' },
  { key: 'username', label: 'Username' },
  { key: 'email', label: 'Email' },
  { key: 'credits', label: 'Credits', width: '110px' },
  { key: 'status', label: 'Status', width: '110px' },
]

async function fetchResellers() {
  loading.value = true
  try {
    const res = await resellersService.list({ page: page.value, per_page: perPage.value })
    resellers.value = res.data || []
    total.value = res.total || 0
  } catch (error) {
    uiStore.toastError('Failed to load resellers')
  } finally {
    loading.value = false
  }
}

function handlePageChange(p) { page.value = p; fetchResellers() }
function handlePerPageChange(n) { perPage.value = n; page.value = 1; fetchResellers() }

onMounted(fetchResellers)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Resellers</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Manage reseller accounts and credits</p>
      </div>
      <router-link to="/resellers/create" class="btn-primary"><PlusIcon class="w-5 h-5" /> Add Reseller</router-link>
    </div>

    <div class="card">
      <DataTable
        :columns="columns"
        :data="resellers"
        :loading="loading"
        :total="total"
        :page="page"
        :per-page="perPage"
        :selectable="false"
        @page-change="handlePageChange"
        @per-page-change="handlePerPageChange"
        @row-click="$event => router.push({ name: 'reseller-detail', params: { id: $event.id } })"
        row-clickable
      >
        <template #cell-status="{ row }">
          <span class="badge" :class="row.enabled ? 'badge-success' : 'badge-danger'">{{ row.enabled ? 'Active' : 'Disabled' }}</span>
        </template>
      </DataTable>
    </div>
  </div>
</template>
