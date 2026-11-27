<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import linesService from '@services/lines'
import usersService from '@services/users'
import { ArrowPathIcon, ClipboardDocumentIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()

const users = ref([])
const saving = ref(false)
const created = ref(null)

const form = reactive({
  username: '',
  password: generatePassword(),
  user_id: '',
  exp_date: '',
  max_connections: 1,
  enabled: true,
})

function generatePassword() {
  const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
  const bytes = new Uint32Array(12)
  crypto.getRandomValues(bytes)

  return Array.from(bytes, byte => alphabet[byte % alphabet.length]).join('')
}

async function loadUsers() {
  try {
    const response = await usersService.list({ per_page: 100 })
    users.value = response.data || []
  } catch (error) {
    // optional owner list
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

async function submit() {
  saving.value = true
  try {
    const response = await linesService.create({ ...form, user_id: form.user_id || null })
    created.value = {
      line: response.data,
      credentials: response.credentials,
    }
    uiStore.toastSuccess('Line created')
  } catch (error) {
    const message = error.response?.data?.errors?.username?.[0] || error.response?.data?.error || 'Failed to create line'
    uiStore.toastError(message)
  } finally {
    saving.value = false
  }
}

function createAnother() {
  created.value = null
  form.username = ''
  form.password = generatePassword()
  form.exp_date = ''
  form.max_connections = 1
  form.enabled = true
}

onMounted(loadUsers)
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Create Line</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">A line is the streaming subscription used by players</p>
    </div>

    <div v-if="created" class="card">
      <div class="card-body space-y-4">
        <div class="flex items-center gap-3">
          <CheckCircleIcon class="w-8 h-8 text-green-500" />
          <div>
            <p class="font-semibold text-dark-900 dark:text-white">Line #{{ created.line?.id }} created</p>
            <p class="text-sm text-dark-500 dark:text-dark-400">Save these credentials now; the password is not stored in clear text.</p>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <p class="label">Username</p>
            <div class="flex items-center gap-2">
              <code class="flex-1 rounded-lg bg-dark-100 dark:bg-dark-800 px-3 py-2">{{ created.credentials.username }}</code>
              <button type="button" class="btn-secondary btn-sm" @click="copy(created.credentials.username)" title="Copy">
                <ClipboardDocumentIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div>
            <p class="label">Password</p>
            <div class="flex items-center gap-2">
              <code class="flex-1 rounded-lg bg-dark-100 dark:bg-dark-800 px-3 py-2">{{ created.credentials.password }}</code>
              <button type="button" class="btn-secondary btn-sm" @click="copy(created.credentials.password)" title="Copy">
                <ClipboardDocumentIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3">
          <button type="button" class="btn-secondary" @click="createAnother">Create another</button>
          <router-link :to="{ name: 'line-detail', params: { id: created.line.id } }" class="btn-primary">Open line</router-link>
          <router-link to="/lines" class="btn-ghost">Back to lines</router-link>
        </div>
      </div>
    </div>

    <form v-else @submit.prevent="submit" class="card">
      <div class="card-body space-y-5">
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label for="username" class="label">Username <span class="text-red-500">*</span></label>
            <input id="username" v-model="form.username" class="input" placeholder="line-username" minlength="3" maxlength="64" required />
            <p class="text-xs text-dark-500 dark:text-dark-400 mt-1">3-64 chars: letters, numbers, `.`, `_`, `-`.</p>
          </div>
          <div>
            <label for="password" class="label">Password</label>
            <div class="flex gap-2">
              <input id="password" v-model="form.password" class="input" minlength="6" required />
              <button type="button" class="btn-secondary" title="Generate" @click="form.password = generatePassword()">
                <ArrowPathIcon class="w-4 h-4" />
              </button>
            </div>
            <p class="text-xs text-dark-500 dark:text-dark-400 mt-1">Minimum 6 characters.</p>
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
            <p class="text-xs text-dark-500 dark:text-dark-400 mt-1">Leave empty for never expires.</p>
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

      <div class="card-footer flex justify-end gap-3">
        <router-link to="/lines" class="btn-secondary">Cancel</router-link>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Creating…' : 'Create Line' }}</button>
      </div>
    </form>
  </div>
</template>
