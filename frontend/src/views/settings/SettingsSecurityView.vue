<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import settingsService from '@services/settings'

const uiStore = useUiStore()
const form = reactive({
  force_two_factor: false,
  debug_show_errors: false,
  rate_limit_per_minute: 120,
  panel_access_code: '',
  allowed_ips: '',
})
const loading = ref(true)
const saving = ref(false)

onMounted(async () => {
  try {
    const res = await settingsService.section('security')
    Object.assign(form, res.data || res)
  } catch (error) {
    uiStore.toastError('Failed to load security settings')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  try {
    await settingsService.updateSection('security', form)
    uiStore.toastSuccess('Security settings saved')
  } catch (error) {
    uiStore.toastError('Failed to save security settings')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="save" class="card">
    <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Security</h3></div>
    <div class="card-body space-y-5">
      <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
        <input v-model="form.force_two_factor" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
        Require two-factor authentication for all admin accounts
      </label>
      <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
        <input v-model="form.debug_show_errors" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
        Show debug error details (never enable in production)
      </label>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label for="rate_limit_per_minute" class="label">API rate limit (requests/min)</label>
          <input id="rate_limit_per_minute" v-model.number="form.rate_limit_per_minute" type="number" min="10" class="input" />
        </div>
        <div>
          <label for="panel_access_code" class="label">Panel access code</label>
          <input id="panel_access_code" v-model="form.panel_access_code" class="input" placeholder="Optional path segment" />
        </div>
      </div>
      <div>
        <label for="allowed_ips" class="label">Allowed IPs (comma separated)</label>
        <textarea id="allowed_ips" v-model="form.allowed_ips" rows="3" class="input" placeholder="203.0.113.4, 198.51.100.0/24" />
      </div>
    </div>
    <div class="card-footer flex justify-end">
      <button type="submit" :disabled="saving || loading" class="btn-primary">{{ saving ? 'Saving…' : 'Save changes' }}</button>
    </div>
  </form>
</template>
