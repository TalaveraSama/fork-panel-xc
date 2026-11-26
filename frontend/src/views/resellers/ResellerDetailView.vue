<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import resellersService from '@services/resellers'
import { formatDateTime } from '@utils/format'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()
const reseller = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await resellersService.get(route.params.id)
    reseller.value = res.data || res
  } catch (error) {
    uiStore.toastError('Failed to load reseller')
    router.push({ name: 'resellers' })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else-if="reseller" class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">{{ reseller.username }}</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">{{ reseller.email || 'No email' }}</p>
      </div>
      <router-link to="/resellers" class="btn-secondary">Back</router-link>
    </div>

    <div class="card">
      <div class="card-body grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div><p class="text-dark-500 dark:text-dark-400">Credits</p><p class="font-medium text-dark-900 dark:text-white">{{ reseller.credits ?? 0 }}</p></div>
        <div><p class="text-dark-500 dark:text-dark-400">Status</p><span class="badge" :class="reseller.enabled ? 'badge-success' : 'badge-danger'">{{ reseller.enabled ? 'Active' : 'Disabled' }}</span></div>
        <div><p class="text-dark-500 dark:text-dark-400">Created</p><p class="text-dark-900 dark:text-white">{{ formatDateTime(reseller.created_at) }}</p></div>
        <div><p class="text-dark-500 dark:text-dark-400">Users</p><p class="text-dark-900 dark:text-white">{{ reseller.users_count ?? 0 }}</p></div>
      </div>
    </div>
  </div>
</template>
