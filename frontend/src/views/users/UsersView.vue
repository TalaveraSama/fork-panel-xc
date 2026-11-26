<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import usersService from '@services/users'
import { formatNumber, getStatusColor } from '@utils/format'
import DataTable from '@components/ui/DataTable.vue'
import { PlusIcon, MagnifyingGlassIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()

// State
const users = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const search = ref('')
const filters = ref({ status: '' })
const sort = ref({ field: 'id', direction: 'desc' })
const selectedIds = ref([])

// Computed
const hasSelection = computed(() => selectedIds.value.length > 0)

// Fetch data
async function fetchUsers() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
      status: filters.value.status || undefined,
      sort_field: sort.value.field,
      sort_direction: sort.value.direction,
    }

    const response = await usersService.list(params)
    users.value = response.data || []
    total.value = response.total || 0
  } catch (error) {
    uiStore.toastError('Failed to load users')
  } finally {
    loading.value = false
  }
}

// Selection
function toggleSelectAll(event) {
  if (event.target.checked) {
    selectedIds.value = users.value.map(u => u.id)
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

function handleSort(field) {
  if (sort.value.field === field) {
    sort.value.direction = sort.value.direction === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value.field = field
    sort.value.direction = 'asc'
  }
  fetchUsers()
}

function handlePageChange(newPage) {
  page.value = newPage
  fetchUsers()
}

function handlePerPageChange(newPerPage) {
  perPage.value = newPerPage
  page.value = 1
  fetchUsers()
}

async function deleteUser(id) {
  if (!window.confirm('Delete this user? This cannot be undone.')) return
  try {
    await usersService.delete(id)
    uiStore.toastSuccess('User deleted')
    fetchUsers()
  } catch (error) {
    uiStore.toastError('Failed to delete user')
  }
}

// Actions
async function bulkDelete() {
  if (!hasSelection.value) return

  const confirmed = window.confirm(`Delete ${selectedIds.value.length} user(s)? This cannot be undone.`)
  if (!confirmed) return

  try {
    await usersService.bulkDelete(selectedIds.value)
    uiStore.toastSuccess(`${selectedIds.value.length} user(s) deleted`)
    selectedIds.value = []
    fetchUsers()
  } catch (error) {
    uiStore.toastError('Failed to delete users')
  }
}

// Table columns
const columns = [
  { key: 'select', label: '', width: '40px', sortable: false },
  { key: 'id', label: 'ID', width: '80px', sortable: true },
  { key: 'username', label: 'Username', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'role', label: 'Role', width: '120px', sortable: true },
  { key: 'status', label: 'Status', width: '100px', sortable: true },
  { key: 'lines', label: 'Lines', width: '80px', sortable: false },
  { key: 'actions', label: 'Actions', width: '120px', sortable: false },
]

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Users</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Manage user accounts and subscriptions</p>
      </div>
      <router-link to="/users/create" class="btn-primary">
        <PlusIcon class="w-5 h-5" />
        Add User
      </router-link>
    </div>

    <!-- Toolbar -->
    <div class="card">
      <div class="card-body px-4 py-4">
        <div class="flex flex-col lg:flex-row gap-4">
          <div class="relative flex-1 max-w-md">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-dark-400" />
            <input
              v-model="search"
              @keyup.enter="fetchUsers"
              type="text"
              placeholder="Search users..."
              class="input pl-10"
            />
          </div>

          <div class="flex items-center gap-3">
            <select v-model="filters.status" @change="fetchUsers" class="input w-auto">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="disabled">Disabled</option>
            </select>

            <button @click="fetchUsers" :disabled="loading" class="btn-secondary">
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
            {{ selectedIds.length }} user(s) selected
          </span>
          <div class="flex items-center gap-2">
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
        :data="users"
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
        @row-click="($event) => router.push({ name: 'user-detail', params: { id: $event.id } })"
      >
        <template #cell-select="{ row }">
          <input
            type="checkbox"
            :checked="selectedIds.includes(row.id)"
            @change="toggleSelect(row.id)"
            @click.stop
            class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500"
          />
        </template>

        <template #cell-username="{ row }">
          <p class="font-medium text-dark-900 dark:text-white">{{ row.username }}</p>
        </template>

        <template #cell-email="{ row }">
          <p class="text-sm text-dark-600 dark:text-dark-400">{{ row.email || '-' }}</p>
        </template>

        <template #cell-role="{ row }">
          <span :class="['badge', row.is_admin ? 'badge-primary' : row.is_reseller ? 'badge-info' : 'badge-success']">
            {{ row.is_admin ? 'Admin' : row.is_reseller ? 'Reseller' : 'User' }}
          </span>
        </template>

        <template #cell-status="{ row }">
          <span :class="['badge', getStatusColor(row.enabled ? 'enabled' : 'disabled')]">
            {{ row.enabled ? 'Active' : 'Disabled' }}
          </span>
        </template>

        <template #cell-lines="{ row }">
          <span class="text-sm text-dark-600 dark:text-dark-400">{{ row.lines_count || 0 }}</span>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center gap-1">
            <router-link
              :to="{ name: 'user-edit', params: { id: row.id } }"
              class="p-1.5 rounded-lg text-dark-500 hover:text-dark-900 dark:text-dark-400 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
              title="Edit"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </router-link>
            <button
              @click.stop="deleteUser(row.id)"
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
    <div v-if="!loading && users.length === 0" class="card">
      <div class="card-body py-16 text-center">
        <svg class="w-16 h-16 mx-auto text-dark-300 dark:text-dark-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <h3 class="text-lg font-medium text-dark-900 dark:text-white mb-2">No users found</h3>
        <p class="text-dark-500 dark:text-dark-400 mb-4">Get started by adding your first user</p>
        <router-link to="/users/create" class="btn-primary inline-flex">
          <PlusIcon class="w-5 h-5" />
          Add User
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'UsersView',
}
</script>