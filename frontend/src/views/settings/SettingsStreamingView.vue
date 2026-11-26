<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import settingsService from '@services/settings'
import tmdbService from '@services/tmdb'
import { StarIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

const form = reactive({
  ffmpeg_version: '7.1',
  segment_time: 6,
  hls_window: 6,
  client_prebuffer: 30,
  max_connections: 1000,
  fanout_enabled: true,
})

const tmdb = reactive({
  available: false,
  has_key: false,
  key_masked: '',
  source: 'none',
  language: 'es-ES',
  api_key: '',
})

const loading = ref(true)
const saving = ref(false)

onMounted(async () => {
  try {
    const res = await settingsService.section('streaming')
    Object.assign(form, res.data || res)
  } catch (error) {
    uiStore.toastError('Failed to load streaming settings')
  }

  try {
    const settings = await tmdbService.settings()
    Object.assign(tmdb, {
      available: true,
      has_key: settings.has_key,
      key_masked: settings.key_masked,
      source: settings.source,
      language: settings.language || 'es-ES',
    })
  } catch (error) {
    tmdb.available = false
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true

  try {
    await settingsService.updateSection('streaming', form)
  } catch (error) {
    uiStore.toastError('Failed to save streaming settings')
    saving.value = false
    return
  }

  if (tmdb.available) {
    const payload = { language: tmdb.language }

    if (tmdb.api_key.trim() !== '') {
      payload.api_key = tmdb.api_key.trim()
    }

    try {
      const settings = await tmdbService.saveSettings(payload)
      Object.assign(tmdb, {
        has_key: settings.has_key,
        key_masked: settings.key_masked,
        source: settings.source,
        language: settings.language || tmdb.language,
        api_key: '',
      })
    } catch (error) {
      uiStore.toastError(error.response?.data?.error || 'TMDB rejected the API key')
      saving.value = false
      return
    }
  }

  uiStore.toastSuccess('Streaming settings saved')
  saving.value = false
}
</script>

<template>
  <form @submit.prevent="save" class="space-y-6">
    <div class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Streaming</h3></div>
      <div class="card-body space-y-5">
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label for="ffmpeg_version" class="label">FFmpeg build</label>
            <select id="ffmpeg_version" v-model="form.ffmpeg_version" class="input">
              <option value="8.1">8.1</option>
              <option value="7.1">7.1</option>
              <option value="4.4">4.4</option>
            </select>
          </div>
          <div>
            <label for="segment_time" class="label">Segment time (s)</label>
            <input id="segment_time" v-model.number="form.segment_time" type="number" min="1" max="30" class="input" />
          </div>
          <div>
            <label for="hls_window" class="label">HLS window</label>
            <input id="hls_window" v-model.number="form.hls_window" type="number" min="3" max="20" class="input" />
          </div>
          <div>
            <label for="client_prebuffer" class="label">Client prebuffer (s)</label>
            <input id="client_prebuffer" v-model.number="form.client_prebuffer" type="number" min="5" max="120" class="input" />
          </div>
          <div>
            <label for="max_connections" class="label">Max connections</label>
            <input id="max_connections" v-model.number="form.max_connections" type="number" min="1" class="input" />
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
          <input v-model="form.fanout_enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
          Enable fanout delivery (<code class="text-xs">xc_fanout</code>)
        </label>

        <p class="text-xs text-dark-500 dark:text-dark-400">
          Ring seconds = max(HLS window × segment time, prebuffer + segment time), capped at 120 s.
        </p>
      </div>
    </div>

    <div class="card">
      <div class="card-header flex items-center justify-between">
        <div>
          <h3 class="font-semibold text-dark-900 dark:text-white">TMDB — metadatos de películas y series</h3>
          <p class="text-xs text-dark-500 dark:text-dark-400 mt-0.5">
            The Movie Database (themoviedb.org): póster, backdrop, sinopsis, año, duración, géneros y valoración.
          </p>
        </div>
        <span class="badge" :class="tmdb.has_key ? 'badge-success' : 'badge-warning'">
          {{ tmdb.has_key ? 'configured' : 'no key' }}
        </span>
      </div>

      <div class="card-body space-y-4">
        <template v-if="tmdb.available">
          <p class="text-sm text-dark-600 dark:text-dark-400">
            API key actual:
            <strong v-if="tmdb.has_key" class="text-dark-900 dark:text-white">{{ tmdb.key_masked }}</strong>
            <strong v-else class="text-red-600 dark:text-red-400">sin configurar</strong>
            <span class="text-dark-400">&nbsp;({{ tmdb.source === 'env' ? 'config/.env' : (tmdb.source === 'database' ? 'base de datos' : '—') }})</span>
          </p>

          <p v-if="tmdb.source === 'env'" class="text-xs text-amber-600 dark:text-amber-400">
            <code>TMDB_API_KEY</code> está definida en <code>config/.env</code> y tiene prioridad.
            Para gestionarla desde el panel, elimina esa línea del archivo y reinicia PHP-FPM.
          </p>

          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label for="tmdb_api_key" class="label">TMDB API key (v3)</label>
              <input
                id="tmdb_api_key"
                v-model="tmdb.api_key"
                type="password"
                class="input"
                placeholder="Pega aquí tu API key de themoviedb.org"
                autocomplete="off"
              />
              <p class="text-xs text-dark-500 dark:text-dark-400 mt-1">
                Se valida contra TMDB al guardar. También puedes usar <code>TMDB_API_KEY</code> en <code>config/.env</code>.
              </p>
            </div>

            <div>
              <label for="tmdb_language" class="label">Idioma de TMDB</label>
              <select id="tmdb_language" v-model="tmdb.language" class="input">
                <option value="es-ES">Español (ES)</option>
                <option value="es-MX">Español (MX)</option>
                <option value="en-US">English (US)</option>
                <option value="pt-BR">Português (BR)</option>
                <option value="fr-FR">Français</option>
                <option value="de-DE">Deutsch</option>
                <option value="it-IT">Italiano</option>
              </select>
            </div>
          </div>

          <p class="text-xs text-dark-500 dark:text-dark-400">
            Consigue una key gratuita en themoviedb.org → Settings → API.
            Detalle del módulo: <code>docs/modules/TMDB.md</code>.
          </p>
        </template>

        <div v-else class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4 text-sm">
          <p class="font-medium text-amber-800 dark:text-amber-300 flex items-center gap-2">
            <StarIcon class="w-4 h-4" /> El módulo TMDB no está instalado o habilitado.
          </p>
          <p class="text-amber-700 dark:text-amber-400 mt-1">
            Instálalo en <router-link to="/modules" class="underline">Modules → TMDB Metadata</router-link>
            o con <code>php console.php module:install tmdb</code>. Después podrás pegar la API key aquí.
          </p>
        </div>
      </div>

      <div class="card-footer flex justify-end">
        <button type="submit" :disabled="saving || loading" class="btn-primary">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
      </div>
    </div>
  </form>
</template>
