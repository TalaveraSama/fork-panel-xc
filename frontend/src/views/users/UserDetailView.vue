<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import usersService from '@services/users'
import { formatDateTime } from '@utils/format'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()

const user = ref(null)
const activity = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [detail, act] = await Promise.all([
      usersService.get(route.params.id),
      usersService.getActivity(route.params.id, { limit: 10 }).catch(() => ({ data: [] })),
    ])
    user.value = detail.data || detail
    activity.value = act.data || []
  } catch (error) {
    uiStore.toastError('Failed to load user')
    router.push({ name: 'users' })
  } finally {
    loading.value = false
  }
})

async function resetPassword() {
  const password = window.prompt('Enter the new password (min 8 characters):')
  if (!password) return
  try {
    await usersService.resetPassword(route.params.id, password)
    uiStore.toastSuccess('Password reset')
  } catch (error) {
    uiStore.toastError('Failed to reset password')
  }
}
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else-if="user" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">{{ user.username }}</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">{{ user.email || 'No email on file' }}</p>
      </div>
      <div class="flex gap-2">
        <button @click="resetPassword" class="btn-secondary">Reset password</button>
        <router-link to="/users" class="btn-secondary">Back</router-link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Account</h3></div>
        <div class="card-body space-y-3 text-sm">
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Role</span><span class="font-medium text-dark-900 dark:text-white">{{ user.is_admin ? 'Admin' : user.is_reseller ? 'Reseller' : 'User' }}</span></div>
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Status</span><span class="badge" :class="user.enabled ? 'badge-success' : 'badge-danger'">{{ user.enabled ? 'Enabled' : 'Disabled' }}</span></div>
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Created</span><span class="text-dark-900 dark:text-white">{{ formatDateTime(user.created_at) }}</span></div>
          <div class="flex justify-between"><span class="text-dark-500 dark:text-dark-400">Last login</span><span class="text-dark-900 dark:text-white">{{ formatDateTime(user.last_login) || '—' }}</span></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Recent activity</h3></div>
        <div class="card-body">
          <ul class="space-y-2 text-sm">
            <li v-for="item in activity" :key="item.id" class="flex justify-between gap-4">
              <span class="text-dark-900 dark:text-white">{{ item.event }}</span>
              <span class="text-dark-500 dark:text-dark-400">{{ formatDateTime(item.timestamp) }}</span>
            </li>
            <li v-if="activity.length === 0" class="text-dark-500 dark:text-dark-400">No activity recorded</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
