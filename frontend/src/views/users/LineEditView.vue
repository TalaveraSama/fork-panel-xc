<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import linesService from '@services/lines'
import usersService from '@services/users'
import { ClipboardDocumentIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()

const users = ref([])
const loading = ref(true)
const saving = ref(false)
const credentials = ref(null)

const form = reactive({
  username: '',
  password: '',
  user_id: '',
  exp_date: '',
  max_connections: 1,
  enabled: true,
})

function dateValue(timestamp) {
  if (!timestamp) return ''

  return new Date(timestamp * 1000).toISOString().slice(0, 10)
}

onMounted(async () => {
  try {
    const [line, userList] = await Promise.all([
      linesService.get(route.params.id),
      usersService.list({ per_page: 100 }).catch(() => ({ data: [] })),
    ])
    users.value = userList.data || []
    Object.assign(form, {
      username: line.username,
      password: '',
      user_id: line.user_id || '',
      exp_date: dateValue(line.exp_date),
      max_connections: line.max_connections || 1,
      enabled: !!line.enabled,
    })
  } catch (error) {
    uiStore.toastError('Failed to load line')
    router.push({ name: 'lines' })
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  try {
    const payload = {
      username: form.username,
      user_id: form.user_id || null,
      exp_date: form.exp_date,
      max_connections: form.max_connections,
      enabled: form.enabled,
    }
    if (form.password.trim() !== '') payload.password = form.password.trim()

    await linesService.update(route.params.id, payload)
    uiStore.toastSuccess('Line updated')
    form.password = ''
  } catch (error) {
    const message = error.response?.data?.errors?.username?.[0] || error.response?.data?.error || 'Failed to update line'
    uiStore.toastError(message)
  } finally {
    saving.value = false
  }
}

async function resetPassword() {
  if (!window.confirm('Generate a new password for this line?')) return

  try {
    const response = await linesService.resetPassword(route.params.id)
    credentials.value = response.credentials
    uiStore.toastSuccess('Password reset')
  } catch (error) {
    uiStore.toastError('Failed to reset password')
  }
}

async function removeLine() {
  if (!window.confirm('Delete this line? Subscribers using it will stop working.')) return

  try {
    await linesService.delete(route.params.id)
    uiStore.toastSuccess('Line deleted')
    router.push({ name: 'lines' })
  } catch (error) {
    uiStore.toastError('Failed to delete line')
  }
}

async function copy(text) {
  try {
    await navigator.clipboard.writeText(text)
    uiStore.toastSuccess('Copied')
  } catch (error) {
    uiStore.toastError('Copy failed')
  }
}
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Edit Line</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Update credentials, expiration and limits</p>
      </div>
      <router-link :to="{ name: 'line-detail', params: { id: route.params.id } }" class="btn-secondary">View</router-link>
    </div>

    <div v-if="credentials" class="card border-green-300 dark:border-green-800">
      <div class="card-body space-y-2">
        <p class="font-medium text-dark-900 dark:text-white">New password generated</p>
        <div class="flex flex-wrap items-center gap-2">
          <code class="rounded-lg bg-dark-100 dark:bg-dark-800 px-3 py-2">{{ credentials.username }} / {{ credentials.password }}</code>
          <button type="button" class="btn-secondary btn-sm" @click="copy(`${credentials.username} / ${credentials.password}`)">
            <ClipboardDocumentIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <form @submit.prevent="save" class="card">
      <div class="card-body space-y-5">
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label for="username" class="label">Username</label>
            <input id="username" v-model="form.username" class="input" minlength="3" maxlength="64" required />
          </div>
          <div>
            <label for="password" class="label">New password (optional)</label>
            <input id="password" v-model="form.password" class="input" minlength="6" placeholder="Leave empty to keep" />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label for="user_id" class="label">Owner user</label>
            <select id="user_id" v-model="form.user_id" class="input">
              <option value="">— Unassigned —</option>
              <option v-for="user in users" :key="user.id" :value="user.id">{{ user.username }}</option>
            </select>
          </div>
          <div>
            <label for="exp_date" class="label">Expiration date</label>
            <input id="exp_date" v-model="form.exp_date" type="date" class="input" />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label for="max_connections" class="label">Max connections</label>
            <input id="max_connections" v-model.number="form.max_connections" type="number" min="1" class="input" />
          </div>
          <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300 sm:mt-7">
            <input v-model="form.enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
            Enabled
          </label>
        </div>
      </div>

      <div class="card-footer flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
          <button type="button" class="btn-secondary" @click="resetPassword">
            <ArrowPathIcon class="w-4 h-4" />
            Reset password
          </button>
          <button type="button" class="btn-danger" @click="removeLine">Delete</button>
        </div>
        <div class="flex gap-3">
          <router-link to="/lines" class="btn-secondary">Cancel</router-link>
          <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Saving…' : 'Save changes' }}</button>
        </div>
      </div>
    </form>
  </div>
</template>
