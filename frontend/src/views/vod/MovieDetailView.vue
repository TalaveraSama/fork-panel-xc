<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import moviesService from '@services/movies'
import { formatDateTime } from '@utils/format'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()
const movie = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await moviesService.get(route.params.id)
    movie.value = res.data || res
  } catch (error) {
    uiStore.toastError('Failed to load movie')
    router.push({ name: 'movies' })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else-if="movie" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">{{ movie.name || movie.stream_display_name }}</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">{{ movie.year || '—' }} · {{ movie.category_name || 'Uncategorized' }}</p>
      </div>
      <router-link to="/movies" class="btn-secondary">Back to Movies</router-link>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="card lg:col-span-2">
        <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Details</h3></div>
        <div class="card-body grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div><p class="text-dark-500 dark:text-dark-400">Source</p><code class="text-dark-900 dark:text-white break-all">{{ movie.stream_source || movie.source }}</code></div>
          <div><p class="text-dark-500 dark:text-dark-400">Created</p><p class="text-dark-900 dark:text-white">{{ formatDateTime(movie.created_at) }}</p></div>
          <div><p class="text-dark-500 dark:text-dark-400">TMDB ID</p><p class="text-dark-900 dark:text-white">{{ movie.tmdb_id || '—' }}</p></div>
          <div><p class="text-dark-500 dark:text-dark-400">Status</p><span class="badge" :class="movie.enabled ? 'badge-success' : 'badge-danger'">{{ movie.enabled ? 'Enabled' : 'Disabled' }}</span></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Poster</h3></div>
        <div class="card-body">
          <img v-if="movie.stream_icon || movie.icon" :src="movie.stream_icon || movie.icon" alt="" class="w-full rounded-lg object-cover aspect-[2/3]" />
          <p v-else class="text-sm text-dark-500 dark:text-dark-400">No poster available</p>
        </div>
      </div>
    </div>
  </div>
</template>
