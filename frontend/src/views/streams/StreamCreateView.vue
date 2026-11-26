<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import streamsService from '@services/streams'

const router = useRouter()
const uiStore = useUiStore()

const form = reactive({
  stream_display_name: '',
  stream_source: '',
  stream_icon: '',
  stream_type: 'live',
  category_id: '',
  enabled: true,
})

const loading = ref(false)
const categories = ref([])
const errors = ref({})

async function fetchCategories() {
  try {
    const response = await streamsService.getCategories()
    categories.value = response.data || []
  } catch (error) {
    console.error('Failed to load categories:', error)
  }
}

async function handleSubmit() {
  errors.value = {}
  loading.value = true
  uiStore.setLoading(true, 'Creating stream...')

  try {
    await streamsService.create(form)
    uiStore.toastSuccess('Stream created successfully')
    router.push({ name: 'streams' })
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      uiStore.toastError('Failed to create stream')
    }
  } finally {
    loading.value = false
    uiStore.setLoading(false)
  }
}

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Add Stream</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Create a new live stream, VOD, or radio channel</p>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="card">
        <div class="card-header">
          <h3 class="text-lg font-semibold text-dark-900 dark:text-white">Stream Information</h3>
        </div>
        <div class="card-body space-y-6">
          <div>
            <label for="stream_display_name" class="label">Stream Name <span class="text-red-500">*</span></label>
            <input
              id="stream_display_name"
              v-model="form.stream_display_name"
              type="text"
              class="input"
              :class="{ 'input-error': errors.stream_display_name }"
              placeholder="Enter stream name"
              required
            />
            <p v-if="errors.stream_display_name" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ errors.stream_display_name[0] }}
            </p>
          </div>

          <div>
            <label for="stream_source" class="label">Source URL <span class="text-red-500">*</span></label>
            <input
              id="stream_source"
              v-model="form.stream_source"
              type="url"
              class="input"
              :class="{ 'input-error': errors.stream_source }"
              placeholder="http://example.com/stream.m3u8"
              required
            />
            <p v-if="errors.stream_source" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ errors.stream_source[0] }}
            </p>
            <p class="mt-1 text-sm text-dark-500 dark:text-dark-400">Supports M3U8, MPEG-TS, HTTP, HTTPS, RTMP</p>
          </div>

          <div>
            <label for="stream_icon" class="label">Icon URL</label>
            <input
              id="stream_icon"
              v-model="form.stream_icon"
              type="url"
              class="input"
              placeholder="http://example.com/icon.png"
            />
            <p class="mt-1 text-sm text-dark-500 dark:text-dark-400">Optional: Stream logo/icon</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="stream_type" class="label">Stream Type</label>
              <select
                id="stream_type"
                v-model="form.stream_type"
                class="input"
              >
                <option value="live">Live TV</option>
                <option value="vod">VOD (Movie)</option>
                <option value="series">Series</option>
                <option value="radio">Radio</option>
              </select>
            </div>

            <div>
              <label for="category_id" class="label">Category</label>
              <select
                id="category_id"
                v-model="form.category_id"
                class="input"
              >
                <option value="">Select category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
          </div>

          <div class="flex items-center">
            <input
              id="enabled"
              v-model="form.enabled"
              type="checkbox"
              class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500"
            />
            <label for="enabled" class="ml-2 text-sm text-dark-700 dark:text-dark-300">Enabled</label>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <router-link to="/streams" class="btn-secondary">Cancel</router-link>
        <button type="submit" :disabled="loading" class="btn-primary">
          <span v-if="loading" class="flex items-center gap-2">
            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            Creating...
          </span>
          <span v-else>Create Stream</span>
        </button>
      </div>
    </form>
  </div>
</template>