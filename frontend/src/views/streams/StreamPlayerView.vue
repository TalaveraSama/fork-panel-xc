<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import Hls from 'hls.js'
import streamsService from '@services/streams'
import { useUiStore } from '@stores/ui'
import { ArrowPathIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const uiStore = useUiStore()

const video = ref(null)
const stream = ref(null)
const preview = ref(null)
const loading = ref(true)
const error = ref('')
let hls = null

async function load() {
  loading.value = true
  error.value = ''
  destroyHls()

  try {
    const [streamData, previewData] = await Promise.all([
      streamsService.get(route.params.id),
      streamsService.preview(route.params.id),
    ])
    stream.value = streamData.data || streamData
    preview.value = previewData

    await nextTick()
    const element = video.value
    if (!element) return

    if (preview.value.type === 'hls') {
      if (Hls.isSupported()) {
        hls = new Hls({ enableWorker: true, lowLatencyMode: false })
        hls.loadSource(preview.value.url)
        hls.attachMedia(element)
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
          error.value = ''
          element.play().catch(() => {})
        })
        hls.on(Hls.Events.FRAG_LOADED, () => {
          if (!element.error && element.readyState >= 2) {
            error.value = ''
          }
        })
        hls.on(Hls.Events.ERROR, (_event, data) => {
          // hls.js reports recoverable segment/level errors as fatal; retry
          // instead of covering a playing video with an error overlay.
          if (!data.fatal) return

          if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
            hls.startLoad()
          } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
            hls.recoverMediaError()
          } else {
            error.value = `HLS error: ${data.type} / ${data.details}`
          }
        })
      } else if (element.canPlayType('application/vnd.apple.mpegurl')) {
        element.src = preview.value.url
        element.play().catch(() => {})
      } else {
        error.value = 'This browser cannot play HLS. Use Chrome/Edge with hls.js or Safari.'
      }
    } else {
      element.src = preview.value.url
      element.play().catch(() => {})
    }
  } catch (exception) {
    error.value = exception.response?.data?.error || 'Failed to load the test player'
    uiStore.toastError(error.value)
  } finally {
    loading.value = false
  }
}

function destroyHls() {
  if (hls) {
    hls.destroy()
    hls = null
  }

  if (video.value) {
    video.value.removeAttribute('src')
    video.value.load()
  }
}

onMounted(load)
onBeforeUnmount(destroyHls)
</script>

<template>
  <div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Test player</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">
          {{ stream?.stream_display_name || 'Loading…' }}
          <span v-if="preview" class="ml-2 badge badge-info">{{ preview.type === 'hls' ? 'HLS' : 'Video' }}</span>
        </p>
      </div>
      <div class="flex gap-2">
        <router-link v-if="stream" :to="{ name: 'stream-detail', params: { id: stream.id } }" class="btn-secondary">
          <ArrowLeftIcon class="w-4 h-4" />
          Back to stream
        </router-link>
        <button class="btn-secondary" :disabled="loading" @click="load">
          <ArrowPathIcon class="w-4 h-4" :class="loading ? 'animate-spin' : ''" />
          Reload
        </button>
      </div>
    </div>

    <div class="card overflow-hidden bg-black">
      <div class="relative aspect-video">
        <video ref="video" class="w-full h-full" controls autoplay playsinline @playing="error = ''" />
        <div v-if="loading" class="absolute inset-0 flex items-center justify-center text-white/70">
          Loading stream…
        </div>
        <div v-else-if="error" class="absolute inset-0 flex items-center justify-center p-6 text-center text-red-300">
          {{ error }}
        </div>
      </div>
    </div>

    <div v-if="stream" class="grid sm:grid-cols-3 gap-4">
      <div class="card p-4">
        <p class="text-sm text-dark-500 dark:text-dark-400">Status</p>
        <p class="font-medium text-dark-900 dark:text-white mt-1">{{ stream.status }}</p>
      </div>
      <div class="card p-4">
        <p class="text-sm text-dark-500 dark:text-dark-400">Type</p>
        <p class="font-medium text-dark-900 dark:text-white mt-1">{{ stream.stream_type }}</p>
      </div>
      <div class="card p-4">
        <p class="text-sm text-dark-500 dark:text-dark-400">Preview expires</p>
        <p class="font-medium text-dark-900 dark:text-white mt-1">15 minutes</p>
      </div>
    </div>

    <p class="text-xs text-dark-500 dark:text-dark-400">
      The player uses a signed admin preview URL (15 min), proxied through the stream gateway, so no line credentials are exposed in the browser.
    </p>
  </div>
</template>
