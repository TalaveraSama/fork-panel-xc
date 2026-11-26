<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import bouquetsService from '@services/bouquets'
import DataTable from '@components/ui/DataTable.vue'
import { PlusIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()

const bouquets = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const showForm = ref(false)
const saving = ref(false)
const form = reactive({ name: '', description: '' })

const columns = [
  { key: 'id', label: 'ID', width: '80px' },
  { key: 'name', label: 'Name' },
  { key: 'description', label: 'Description' },
  { key: 'streams', label: 'Streams', width: '110px' },
]

async function fetchBouquets() {
  loading.value = true
  try {
    const res = await bouquetsService.list({ page: page.value, per_page: perPage.value })
    bouquets.value = res.data || []
    total.value = res.total || 0
  } catch (error) {
    uiStore.toastError('Failed to load bouquets')
  } finally {
    loading.value = false
  }
}

async function createBouquet() {
  saving.value = true
  try {
    await bouquetsService.create(form)
    uiStore.toastSuccess('Bouquet created')
    showForm.value = false
    form.name = ''
    form.description = ''
    await fetchBouquets()
  } catch (error) {
    uiStore.toastError('Failed to create bouquet')
  } finally {
    saving.value = false
  }
}

function handlePageChange(p) { page.value = p; fetchBouquets() }
function handlePerPageChange(n) { perPage.value = n; page.value = 1; fetchBouquets() }

onMounted(fetchBouquets)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Bouquets</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Group streams into subscription packages</p>
      </div>
      <button @click="showForm = !showForm" class="btn-primary"><PlusIcon class="w-5 h-5" /> Add Bouquet</button>
    </div>

    <form v-if="showForm" @submit.prevent="createBouquet" class="card">
      <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label for="name" class="label">Name</label>
          <input id="name" v-model="form.name" class="input" required />
        </div>
        <div>
          <label for="description" class="label">Description</label>
          <input id="description" v-model="form.description" class="input" />
        </div>
      </div>
      <div class="card-footer flex justify-end gap-3">
        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Saving…' : 'Create Bouquet' }}</button>
      </div>
    </form>

    <div class="card">
      <DataTable
        :columns="columns"
        :data="bouquets"
        :loading="loading"
        :total="total"
        :page="page"
        :per-page="perPage"
        :selectable="false"
        @page-change="handlePageChange"
        @per-page-change="handlePerPageChange"
        @row-click="$event => router.push({ name: 'bouquet-detail', params: { id: $event.id } })"
        row-clickable
      >
        <template #cell-name="{ row }">
          <span class="font-medium text-dark-900 dark:text-white">{{ row.name }}</span>
        </template>
        <template #cell-streams="{ row }">{{ row.streams_count ?? 0 }}</template>
      </DataTable>
    </div>
  </div>
</template>
