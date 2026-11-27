<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import seriesService from '@services/series'
import streamsService from '@services/streams'
import TmdbPicker from '@components/tmdb/TmdbPicker.vue'
import { StarIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()
const saving = ref(false)
const tmdb = ref(null)
const categories = ref([])

const form = reactive({
  name: '',
  original_title: '',
  overview: '',
  cover: '',
  backdrop: '',
  rating: '',
  genres: '',
  first_air_date: '',
  tmdb_id: '',
  category_id: '',
  enabled: true,
})

onMounted(async () => {
  try {
    const response = await streamsService.getCategories()
    categories.value = (response.data || []).filter((category) => {
      const type = category.type || category.category_type
      return type === 'series'
    })
  } catch (error) {
    // categories are optional
  }
})

function applyTmdb(data) {
  tmdb.value = data
  form.name = data.title || form.name
  form.original_title = data.original_title || ''
  form.overview = data.overview || ''
  form.cover = data.poster_url || form.cover
  form.backdrop = data.backdrop_url || ''
  form.rating = data.rating || ''
  form.genres = Array.isArray(data.genres) ? data.genres.join(', ') : (data.genres || '')
  form.first_air_date = data.release_date || ''
  form.tmdb_id = data.id || ''
}

async function submit() {
  saving.value = true
  try {
    await seriesService.create(form)
    uiStore.toastSuccess('Series created')
    router.push({ name: 'series' })
  } catch (error) {
    uiStore.toastError('Failed to create series')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Add Series</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Busca la serie en TMDB o rellena los datos manualmente</p>
    </div>

    <TmdbPicker type="tv" @select="applyTmdb" />

    <div v-if="tmdb" class="card">
      <div class="card-body flex flex-col sm:flex-row gap-5">
        <img v-if="form.cover" :src="form.cover" alt="" class="w-28 rounded-lg object-cover aspect-[2/3]" />
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-lg font-semibold text-dark-900 dark:text-white">{{ form.name }}</h3>
            <span v-if="form.rating" class="inline-flex items-center gap-1 text-sm text-amber-600 dark:text-amber-400">
              <StarIcon class="w-4 h-4" />{{ form.rating }}
            </span>
            <span v-if="form.first_air_date" class="badge badge-info">{{ form.first_air_date }}</span>
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
            <input id="name" v-model="form.name" class="input" placeholder="Series title" required />
          </div>

          <div>
            <label for="cover" class="label">Cover URL</label>
            <input id="cover" v-model="form.cover" type="url" class="input" placeholder="https://image.tmdb.org/…" />
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
            <label for="first_air_date" class="label">First air date</label>
            <input id="first_air_date" v-model="form.first_air_date" type="date" class="input" />
          </div>

          <div>
            <label for="rating" class="label">Rating (0-10)</label>
            <input id="rating" v-model="form.rating" type="number" min="0" max="10" step="0.1" class="input" />
          </div>

          <div>
            <label for="genres" class="label">Genres</label>
            <input id="genres" v-model="form.genres" class="input" placeholder="Drama, Crimen" />
          </div>

          <div>
            <label for="tmdb_id" class="label">TMDB ID</label>
            <input id="tmdb_id" v-model="form.tmdb_id" type="number" class="input" />
          </div>

          <div>
            <label for="category_id" class="label">Category</label>
            <select id="category_id" v-model="form.category_id" class="input">
              <option value="">Uncategorized</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
          </div>

          <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
            <input v-model="form.enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
            Enabled
          </label>
        </div>
      </div>
      <div class="card-footer flex justify-end gap-3">
        <router-link to="/series" class="btn-secondary">Cancel</router-link>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Creating…' : 'Create Series' }}</button>
      </div>
    </form>
  </div>
</template>
