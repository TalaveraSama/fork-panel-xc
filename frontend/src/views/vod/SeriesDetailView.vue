<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import seriesService from '@services/series'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()

const series = ref(null)
const episodes = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [detail, eps] = await Promise.all([
      seriesService.get(route.params.id),
      seriesService.getEpisodes(route.params.id),
    ])
    series.value = detail.data || detail
    episodes.value = eps.data || []
  } catch (error) {
    uiStore.toastError('Failed to load series')
    router.push({ name: 'series' })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else-if="series" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">{{ series.name || series.title }}</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">{{ episodes.length }} episode(s)</p>
      </div>
      <router-link to="/series" class="btn-secondary">Back to Series</router-link>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Episodes</h3></div>
      <div class="table-container border-0 rounded-none">
        <table class="table">
          <thead>
            <tr>
              <th class="w-24">Season</th>
              <th class="w-24">Episode</th>
              <th>Title</th>
              <th class="w-32">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ep in episodes" :key="ep.id">
              <td>{{ ep.season_num ?? ep.season ?? '—' }}</td>
              <td>{{ ep.episode_num ?? ep.episode ?? '—' }}</td>
              <td class="font-medium text-dark-900 dark:text-white">{{ ep.title || ep.name }}</td>
              <td><span class="badge" :class="ep.enabled ? 'badge-success' : 'badge-danger'">{{ ep.enabled ? 'Enabled' : 'Disabled' }}</span></td>
            </tr>
            <tr v-if="episodes.length === 0">
              <td colspan="4" class="text-center py-10 text-dark-400">No episodes yet</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
