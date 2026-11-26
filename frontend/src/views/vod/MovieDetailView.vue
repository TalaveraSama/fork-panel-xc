<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import moviesService from '@services/movies'
import { formatDateTime } from '@utils/format'
import { StarIcon } from '@heroicons/vue/24/outline'

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
    <div
      class="relative rounded-xl overflow-hidden bg-dark-900 min-h-[14rem] flex items-end"
      :style="movie.backdrop ? { backgroundImage: `linear-gradient(to top, rgba(15,23,42,.95), rgba(15,23,42,.25)), url('${movie.backdrop}')`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}"
    >
      <div class="relative z-10 p-6 flex flex-col sm:flex-row gap-5 items-start sm:items-end w-full">
        <img
          v-if="movie.stream_icon"
          :src="movie.stream_icon"
          alt=""
          class="w-28 rounded-lg shadow-lg object-cover aspect-[2/3] border border-white/20"
        />
        <div class="flex-1 min-w-0 text-white">
          <h1 class="text-2xl font-bold">{{ movie.stream_display_name || movie.name }}</h1>
          <p v-if="movie.original_title && movie.original_title !== movie.stream_display_name" class="text-sm text-white/70 mt-0.5">
            {{ movie.original_title }}
          </p>
          <div class="flex items-center gap-2 flex-wrap mt-2 text-sm">
            <span v-if="movie.rating" class="inline-flex items-center gap-1 text-amber-300"><StarIcon class="w-4 h-4" />{{ movie.rating }}</span>
            <span v-if="movie.year" class="badge badge-info">{{ movie.year }}</span>
            <span v-if="movie.runtime" class="badge badge-primary">{{ movie.runtime }} min</span>
            <span v-if="movie.genres" class="badge bg-white/10 text-white">{{ movie.genres }}</span>
            <span class="badge" :class="movie.enabled ? 'badge-success' : 'badge-danger'">{{ movie.enabled ? 'Enabled' : 'Disabled' }}</span>
          </div>
        </div>
        <router-link to="/movies" class="btn-secondary">Back to Movies</router-link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="card lg:col-span-2">
        <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Overview</h3></div>
        <div class="card-body text-sm text-dark-700 dark:text-dark-300 leading-relaxed">
          <p v-if="movie.overview" class="whitespace-pre-line">{{ movie.overview }}</p>
          <p v-else class="text-dark-500 dark:text-dark-400">No synopsis stored for this movie.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Details</h3></div>
        <div class="card-body space-y-3 text-sm">
          <div><p class="text-dark-500 dark:text-dark-400">Category</p><p class="text-dark-900 dark:text-white">{{ movie.category_name || 'Uncategorized' }}</p></div>
          <div><p class="text-dark-500 dark:text-dark-400">Source</p><code class="text-dark-900 dark:text-white break-all">{{ movie.stream_source || movie.source }}</code></div>
          <div><p class="text-dark-500 dark:text-dark-400">TMDB ID</p><p class="text-dark-900 dark:text-white">{{ movie.tmdb_id || '—' }}</p></div>
          <div><p class="text-dark-500 dark:text-dark-400">Created</p><p class="text-dark-900 dark:text-white">{{ formatDateTime(movie.created_at) }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>
