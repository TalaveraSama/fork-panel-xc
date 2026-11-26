<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import settingsService from '@services/settings'

const uiStore = useUiStore()
const form = reactive({
  cluster_mode: 0,
  main_url: '',
  node_name: '',
  lb_lease_fence: false,
  release_canary: 0,
})
const loading = ref(true)
const saving = ref(false)

const modes = [
  { value: 0, label: '0 — Main with database access' },
  { value: 1, label: '1 — Load balancer (legacy DB grant)' },
  { value: 2, label: '2 — API-only node (no DB credentials)' },
]

onMounted(async () => {
  try {
    const res = await settingsService.section('cluster')
    Object.assign(form, res.data || res)
  } catch (error) {
    uiStore.toastError('Failed to load cluster settings')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  try {
    await settingsService.updateSection('cluster', form)
    uiStore.toastSuccess('Cluster settings saved')
  } catch (error) {
    uiStore.toastError('Failed to save cluster settings')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="save" class="card">
    <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Cluster</h3></div>
    <div class="card-body space-y-5">
      <div>
        <label for="cluster_mode" class="label">Cluster mode</label>
        <select id="cluster_mode" v-model.number="form.cluster_mode" class="input">
          <option v-for="mode in modes" :key="mode.value" :value="mode.value">{{ mode.label }}</option>
        </select>
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label for="main_url" class="label">Main panel URL</label>
          <input id="main_url" v-model="form.main_url" type="url" class="input" placeholder="https://main.example.com" />
        </div>
        <div>
          <label for="node_name" class="label">Node name</label>
          <input id="node_name" v-model="form.node_name" class="input" placeholder="lb-1" />
        </div>
      </div>
      <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
        <input v-model="form.lb_lease_fence" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
        Stop serving when the lease expires (<code class="text-xs">lb_lease_fence</code>)
      </label>
      <div>
        <label for="release_canary" class="label">Release canary server ID (0 = off)</label>
        <input id="release_canary" v-model.number="form.release_canary" type="number" min="0" class="input max-w-[10rem]" />
      </div>
      <p class="text-xs text-dark-500 dark:text-dark-400">
        Cluster nodes require a free activation key. Adding new LB nodes needs an activation key even when attribution is intact.
      </p>
    </div>
    <div class="card-footer flex justify-end">
      <button type="submit" :disabled="saving || loading" class="btn-primary">{{ saving ? 'Saving…' : 'Save changes' }}</button>
    </div>
  </form>
</template>
