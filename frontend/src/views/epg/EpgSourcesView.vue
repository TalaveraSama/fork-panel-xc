<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import epgService from '@services/epg'
import { PlusIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const sources = ref([])
const loading = ref(false)
const showForm = ref(false)
const saving = ref(false)
const syncing = ref(null)

const form = reactive({ name: '', url: '', enabled: true })

async function fetchSources() {
  loading.value = true
  try {
    const res = await epgService.sources()
    sources.value = res.data || []
  } catch (error) {
    uiStore.toastError('Failed to load EPG sources')
  } finally {
    loading.value = false
  }
}

async function createSource() {
  saving.value = true
  try {
    await epgService.createSource(form)
    uiStore.toastSuccess('EPG source added')
    showForm.value = false
    form.name = ''
    form.url = ''
    await fetchSources()
  } catch (error) {
    uiStore.toastError('Failed to add source')
  } finally {
    saving.value = false
  }
}

async function syncSource(id) {
  syncing.value = id
  try {
    await epgService.syncSource(id)
    uiStore.toastSuccess('Sync started')
  } catch (error) {
    uiStore.toastError('Failed to start sync')
  } finally {
    syncing.value = null
  }
}

async function removeSource(id) {
  if (!window.confirm('Delete this EPG source?')) return
  try {
    await epgService.deleteSource(id)
    uiStore.toastSuccess('Source deleted')
    await fetchSources()
  } catch (error) {
    uiStore.toastError('Failed to delete source')
  }
}

onMounted(fetchSources)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">EPG Sources</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">XMLTV feeds used to build the programme guide</p>
      </div>
      <button @click="showForm = !showForm" class="btn-primary"><PlusIcon class="w-5 h-5" /> Add Source</button>
    </div>

    <form v-if="showForm" @submit.prevent="createSource" class="card">
      <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label for="name" class="label">Name</label>
          <input id="name" v-model="form.name" class="input" required />
        </div>
        <div class="md:col-span-2">
          <label for="url" class="label">XMLTV URL</label>
          <input id="url" v-model="form.url" type="url" class="input" placeholder="https://…/guide.xml.gz" required />
        </div>
      </div>
      <div class="card-footer flex justify-end gap-3">
        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Saving…' : 'Add Source' }}</button>
      </div>
    </form>

    <div class="card">
      <div class="table-container border-0 rounded-none">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>URL</th><th class="w-32">Status</th><th class="w-40">Actions</th></tr>
          </thead>
          <tbody>
            <tr v-for="source in sources" :key="source.id">
              <td class="font-medium text-dark-900 dark:text-white">{{ source.name }}</td>
              <td><code class="text-xs text-dark-500 dark:text-dark-400 break-all">{{ source.url }}</code></td>
              <td><span class="badge" :class="source.enabled ? 'badge-success' : 'badge-danger'">{{ source.enabled ? 'Enabled' : 'Disabled' }}</span></td>
              <td>
                <div class="flex gap-2">
                  <button @click="syncSource(source.id)" :disabled="syncing === source.id" class="btn-secondary btn-sm">
                    <ArrowPathIcon class="w-4 h-4" :class="syncing === source.id ? 'animate-spin' : ''" /> Sync
                  </button>
                  <button @click="removeSource(source.id)" class="btn-danger btn-sm">Delete</button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && sources.length === 0">
              <td colspan="4" class="text-center py-10 text-dark-400">No EPG sources configured</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
