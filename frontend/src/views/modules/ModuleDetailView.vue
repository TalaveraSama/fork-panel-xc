<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import modulesService from '@services/modules'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()
const module = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await modulesService.get(route.params.id)
    module.value = res.data || res
  } catch (error) {
    uiStore.toastError('Failed to load module')
    router.push({ name: 'modules' })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else-if="module" class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">{{ module.name }}</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Version {{ module.version }} · {{ module.author }}</p>
      </div>
      <router-link to="/modules" class="btn-secondary">Back</router-link>
    </div>

    <div class="card">
      <div class="card-body space-y-3 text-sm">
        <p class="text-dark-600 dark:text-dark-400">{{ module.description }}</p>
        <p><span class="text-dark-500 dark:text-dark-400">Status:</span> <span class="badge" :class="module.status === 'enabled' ? 'badge-success' : 'badge-warning'">{{ module.status }}</span></p>
        <div v-if="module.permissions?.length">
          <p class="text-dark-500 dark:text-dark-400 mb-1">Permissions</p>
          <div class="flex flex-wrap gap-2">
            <span v-for="permission in module.permissions" :key="permission" class="badge badge-info">{{ permission }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
