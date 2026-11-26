<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import moviesService from '@services/movies'

const router = useRouter()
const uiStore = useUiStore()
const saving = ref(false)

const form = reactive({
  name: '',
  stream_source: '',
  stream_icon: '',
  year: '',
  category_id: '',
  enabled: true,
})

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
  <div class="max-w-2xl mx-auto space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Add Movie</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Create a new VOD movie entry</p>
    </div>

    <form @submit.prevent="submit" class="card">
      <div class="card-body space-y-5">
        <div>
          <label for="name" class="label">Title <span class="text-red-500">*</span></label>
          <input id="name" v-model="form.name" class="input" placeholder="Movie title" required />
        </div>
        <div>
          <label for="stream_source" class="label">Source URL <span class="text-red-500">*</span></label>
          <input id="stream_source" v-model="form.stream_source" type="url" class="input" placeholder="https://…/movie.mp4" required />
        </div>
        <div>
          <label for="stream_icon" class="label">Poster URL</label>
          <input id="stream_icon" v-model="form.stream_icon" type="url" class="input" placeholder="https://…/poster.jpg" />
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label for="year" class="label">Year</label>
            <input id="year" v-model="form.year" type="number" min="1900" max="2100" class="input" />
          </div>
          <div>
            <label for="category_id" class="label">Category ID</label>
            <input id="category_id" v-model="form.category_id" type="number" class="input" placeholder="Optional" />
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
          <input v-model="form.enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
          Enabled
        </label>
      </div>
      <div class="card-footer flex justify-end gap-3">
        <router-link to="/movies" class="btn-secondary">Cancel</router-link>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Creating…' : 'Create Movie' }}</button>
      </div>
    </form>
  </div>
</template>
