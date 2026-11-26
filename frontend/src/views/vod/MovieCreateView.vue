<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import moviesService from '@services/movies'
import TmdbPicker from '@components/tmdb/TmdbPicker.vue'
import { StarIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()
const saving = ref(false)
const tmdb = ref(null)

const form = reactive({
  name: '',
  original_title: '',
  stream_source: '',
  stream_icon: '',
  backdrop: '',
  overview: '',
  year: '',
  runtime: '',
  rating: '',
  genres: '',
  tmdb_id: '',
  category_id: '',
  enabled: true,
})

function applyTmdb(data) {
  tmdb.value = data
  form.name = data.title || form.name
  form.original_title = data.original_title || ''
  form.overview = data.overview || ''
  form.stream_icon = data.poster_url || form.stream_icon
  form.backdrop = data.backdrop_url || ''
  form.year = data.year || ''
  form.rating = data.rating || ''
  form.runtime = data.runtime || ''
  form.genres = Array.isArray(data.genres) ? data.genres.join(', ') : (data.genres || '')
  form.tmdb_id = data.id || ''
}

async function submit() {
  saving.value = true
  try {
    await moviesService.create(form)
    uiStore.toastSuccess('Movie created')
    router.push({ name: 'movies' })
  } catch (error) {
    uiStore.toastError('Failed to create movie')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Add Movie</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Busca en TMDB o rellena los datos manualmente</p>
    </div>

    <TmdbPicker type="movie" @select="applyTmdb" />

    <div v-if="tmdb" class="card">
      <div class="card-body flex flex-col sm:flex-row gap-5">
        <img v-if="form.stream_icon" :src="form.stream_icon" alt="" class="w-28 rounded-lg object-cover aspect-[2/3]" />
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-lg font-semibold text-dark-900 dark:text-white">{{ form.name }}</h3>
            <span v-if="form.rating" class="inline-flex items-center gap-1 text-sm text-amber-600 dark:text-amber-400">
              <StarIcon class="w-4 h-4" />{{ form.rating }}
            </span>
            <span v-if="form.year" class="badge badge-info">{{ form.year }}</span>
            <span v-if="form.runtime" class="badge badge-primary">{{ form.runtime }} min</span>
          </div>
          <p v-if="form.genres" class="text-sm text-dark-500 dark:text-dark-400 mt-1">{{ form.genres }}</p>
          <p v-if="form.overview" class="text-sm text-dark-600 dark:text-dark-300 mt-2 line-clamp-4">{{ form.overview }}</p>
          <p class="text-xs text-dark-400 mt-2">TMDB ID: {{ form.tmdb_id }}</p>
        </div>
      </div>
    </div>

    <form @submit.prevent="submit" class="card">
      <div class="card-body space-y-5">
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label for="name" class="label">Title <span class="text-red-500">*</span></label>
            <input id="name" v-model="form.name" class="input" placeholder="Movie title" required />
          </div>

          <div class="sm:col-span-2">
            <label for="stream_source" class="label">Source URL <span class="text-red-500">*</span></label>
            <input id="stream_source" v-model="form.stream_source" type="url" class="input" placeholder="https://…/movie.mp4" required />
          </div>

          <div>
            <label for="stream_icon" class="label">Poster URL</label>
            <input id="stream_icon" v-model="form.stream_icon" type="url" class="input" placeholder="https://image.tmdb.org/…" />
          </div>

          <div>
            <label for="backdrop" class="label">Backdrop URL</label>
            <input id="backdrop" v-model="form.backdrop" type="url" class="input" placeholder="https://image.tmdb.org/…" />
          </div>

          <div class="sm:col-span-2">
            <label for="overview" class="label">Overview</label>
            <textarea id="overview" v-model="form.overview" rows="3" class="input" placeholder="Sinopsis…" />
          </div>

          <div>
            <label for="year" class="label">Year</label>
            <input id="year" v-model="form.year" type="number" min="1900" max="2100" class="input" />
          </div>

          <div>
            <label for="runtime" class="label">Runtime (min)</label>
            <input id="runtime" v-model="form.runtime" type="number" min="1" class="input" />
          </div>

          <div>
            <label for="rating" class="label">Rating (0-10)</label>
            <input id="rating" v-model="form.rating" type="number" min="0" max="10" step="0.1" class="input" />
          </div>

          <div>
            <label for="genres" class="label">Genres</label>
            <input id="genres" v-model="form.genres" class="input" placeholder="Acción, Ciencia ficción" />
          </div>

          <div>
            <label for="category_id" class="label">Category ID</label>
            <input id="category_id" v-model="form.category_id" type="number" class="input" placeholder="Optional" />
          </div>

          <div>
            <label for="tmdb_id" class="label">TMDB ID</label>
            <input id="tmdb_id" v-model="form.tmdb_id" type="number" class="input" />
          </div>

          <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
            <input v-model="form.enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
            Enabled
          </label>
        </div>
      </div>
      <div class="card-footer flex justify-end gap-3">
        <router-link to="/movies" class="btn-secondary">Cancel</router-link>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Creating…' : 'Create Movie' }}</button>
      </div>
    </form>
  </div>
</template>
