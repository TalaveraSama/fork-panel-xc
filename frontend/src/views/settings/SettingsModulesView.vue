<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useUiStore } from '@stores/ui'
import modulesService from '@services/modules'
import tmdbService from '@services/tmdb'
import libraryService from '@services/library'
import { CheckCircleIcon, KeyIcon, FolderOpenIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

const modules = ref([])
const loading = ref(true)
const busy = ref(false)

const tmdbSettings = ref(null)
const tmdbForm = reactive({ api_key: '', language: 'es-ES' })

const library = reactive({
  available: false,
  movie_paths: '',
  series_paths: '',
  extensions: 'mkv,mp4,avi,mov,m4v,webm,ts',
  scan_interval_minutes: 60,
  enrich_tmdb: true,
  enrich_limit: 25,
  auto_enabled: true,
  mark_missing: true,
  last_report: null,
  last_scan_at: 0,
  stats: null,
  default_movie_path: '',
  default_series_path: '',
  tmdb_available: false,
})
const scanning = ref(false)

const tmdbInstalled = computed(() => modules.value.some(m => m.id === 'tmdb'))
const tmdbAvailable = computed(() => modules.value.some(m => m.id === 'tmdb' && m.available))
const libraryInstalled = computed(() => modules.value.some(m => m.id === 'library'))
const libraryAvailable = computed(() => modules.value.some(m => m.id === 'library' && m.available))

async function load() {
  loading.value = true
  try {
    const response = await modulesService.list()
    const installed = (response.installed || []).map(m => ({ ...m, available: false }))
    const available = (response.available || []).map(m => ({
      id: m.id, name: m.name, version: m.version, description: m.description,
      status: 'available', available: true,
    }))
    modules.value = [...installed, ...available]

    if (tmdbInstalled.value) await loadTmdbSettings()
    if (libraryInstalled.value) await loadLibrarySettings()
  } catch (error) {
    uiStore.toastError('Failed to load modules')
  } finally {
    loading.value = false
  }
}

async function loadTmdbSettings() {
  try {
    tmdbSettings.value = await tmdbService.settings()
    tmdbForm.language = tmdbSettings.value.language || 'es-ES'
  } catch (error) {
    uiStore.toastError(error.response?.data?.error || 'Failed to load TMDB settings')
  }
}

async function loadLibrarySettings() {
  try {
    const settings = await libraryService.settings()
    Object.assign(library, {
      available: true,
      movie_paths: settings.movie_paths || '',
      series_paths: settings.series_paths || '',
      extensions: settings.extensions || library.extensions,
      scan_interval_minutes: settings.scan_interval_minutes || 60,
      enrich_tmdb: !!settings.enrich_tmdb,
      enrich_limit: settings.enrich_limit ?? 25,
      auto_enabled: !!settings.auto_enabled,
      mark_missing: !!settings.mark_missing,
      last_report: settings.last_report || null,
      last_scan_at: settings.last_scan_at || 0,
      stats: settings.stats || null,
      default_movie_path: settings.default_movie_path || '',
      default_series_path: settings.default_series_path || '',
      tmdb_available: !!settings.tmdb_available,
    })
  } catch (error) {
    library.available = false
  }
}

async function installModule(id) {
  busy.value = true
  try {
    await modulesService.install(id)
    try { await modulesService.enable(id) } catch { /* already enabled */ }
    uiStore.toastSuccess(`Module ${id} installed`)
    await load()
  } catch (error) {
    uiStore.toastError(error.response?.data?.error || `Failed to install ${id}`)
  } finally {
    busy.value = false
  }
}

async function saveTmdb() {
  busy.value = true
  try {
    const payload = { language: tmdbForm.language }
    if (tmdbForm.api_key.trim() !== '') payload.api_key = tmdbForm.api_key.trim()
    tmdbSettings.value = await tmdbService.saveSettings(payload)
    tmdbForm.api_key = ''
    uiStore.toastSuccess('TMDB settings saved')
  } catch (error) {
    uiStore.toastError(error.response?.data?.error || 'TMDB rejected the settings')
  } finally {
    busy.value = false
  }
}

async function saveLibrary() {
  busy.value = true
  try {
    const settings = await libraryService.saveSettings({
      movie_paths: library.movie_paths,
      series_paths: library.series_paths,
      extensions: library.extensions,
      scan_interval_minutes: library.scan_interval_minutes,
      enrich_tmdb: library.enrich_tmdb,
      enrich_limit: library.enrich_limit,
      auto_enabled: library.auto_enabled,
      mark_missing: library.mark_missing,
    })
    Object.assign(library, {
      movie_paths: settings.movie_paths,
      series_paths: settings.series_paths,
      extensions: settings.extensions,
      stats: settings.stats,
      tmdb_available: settings.tmdb_available,
    })
    uiStore.toastSuccess('Library settings saved')
  } catch (error) {
    const details = error.response?.data?.errors?.paths
    uiStore.toastError(Array.isArray(details) ? details[0] : (error.response?.data?.error || 'Failed to save library settings'))
  } finally {
    busy.value = false
  }
}

async function runScan(dryRun) {
  scanning.value = true
  try {
    const response = await libraryService.scan({ dry_run: dryRun, limit: 0 })
    library.last_report = response.data
    library.stats = response.stats
    library.last_scan_at = Math.floor(Date.now() / 1000)
    uiStore.toastSuccess(dryRun ? 'Dry run finished' : 'Library scan finished')
  } catch (error) {
    uiStore.toastError(error.response?.data?.error || 'Scan failed')
  } finally {
    scanning.value = false
  }
}

function formatDate(timestamp) {
  return timestamp ? new Date(timestamp * 1000).toLocaleString() : 'never'
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="card">
      <div class="card-header flex items-center justify-between">
        <div>
          <h3 class="font-semibold text-dark-900 dark:text-white">TMDB Metadata</h3>
          <p class="text-xs text-dark-500 dark:text-dark-400 mt-0.5">
            Póster, backdrop, sinopsis, año, duración y géneros al añadir películas y series.
          </p>
        </div>
        <span class="badge" :class="tmdbInstalled ? 'badge-success' : 'badge-warning'">
          {{ tmdbInstalled ? 'installed' : 'not installed' }}
        </span>
      </div>

      <div class="card-body space-y-4">
        <p v-if="loading" class="text-sm text-dark-400">Loading…</p>

        <template v-else>
          <div v-if="!tmdbInstalled" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="text-sm text-dark-600 dark:text-dark-400">Instala el módulo para importar metadatos desde themoviedb.org.</p>
            <button class="btn-primary" :disabled="busy || !tmdbAvailable" @click="installModule('tmdb')">
              {{ busy ? 'Installing…' : 'Install module' }}
            </button>
          </div>

          <template v-else>
            <div class="flex items-center gap-2 text-sm">
              <CheckCircleIcon class="w-5 h-5 text-green-500" />
              <span class="text-dark-700 dark:text-dark-300">
                API key:
                <strong v-if="tmdbSettings?.has_key" class="text-dark-900 dark:text-white">{{ tmdbSettings.key_masked }}</strong>
                <strong v-else class="text-red-600 dark:text-red-400">not configured</strong>
                <span class="text-dark-400">({{ tmdbSettings?.source || 'none' }})</span>
              </span>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label for="tmdb_key" class="label">TMDB API key</label>
                <div class="relative">
                  <KeyIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-dark-400" />
                  <input id="tmdb_key" v-model="tmdbForm.api_key" type="password" class="input pl-10" placeholder="Pega aquí tu v3 API key" autocomplete="off" />
                </div>
                <p class="text-xs text-dark-500 dark:text-dark-400 mt-1">Se valida contra TMDB. También puedes usar TMDB_API_KEY en config/.env.</p>
              </div>
              <div>
                <label for="tmdb_lang" class="label">Language</label>
                <select id="tmdb_lang" v-model="tmdbForm.language" class="input">
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

            <div class="flex justify-end">
              <button class="btn-primary" :disabled="busy" @click="saveTmdb">{{ busy ? 'Saving…' : 'Save TMDB settings' }}</button>
            </div>
          </template>
        </template>
      </div>
    </div>

    <div class="card">
      <div class="card-header flex items-center justify-between">
        <div>
          <h3 class="font-semibold text-dark-900 dark:text-white">Library Scanner</h3>
          <p class="text-xs text-dark-500 dark:text-dark-400 mt-0.5">
            Indexa películas y series desde carpetas locales del VPS (nombres tipo <code>Matrix.1999.1080p.mkv</code> o <code>Show.S01E02.mkv</code>).
          </p>
        </div>
        <span class="badge" :class="libraryInstalled ? 'badge-success' : 'badge-warning'">
          {{ libraryInstalled ? 'installed' : 'not installed' }}
        </span>
      </div>

      <div class="card-body space-y-5">
        <p v-if="loading" class="text-sm text-dark-400">Loading…</p>

        <template v-else>
          <div v-if="!libraryInstalled" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="text-sm text-dark-600 dark:text-dark-400">Instala el módulo para escanear carpetas locales.</p>
            <button class="btn-primary" :disabled="busy || !libraryAvailable" @click="installModule('library')">
              {{ busy ? 'Installing…' : 'Install module' }}
            </button>
          </div>

          <template v-else>
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label for="movie_paths" class="label">Carpetas de películas</label>
                <textarea id="movie_paths" v-model="library.movie_paths" rows="3" class="input font-mono text-xs" :placeholder="library.default_movie_path || '/home/fork_panel_xc/media/movies'" />
              </div>
              <div>
                <label for="series_paths" class="label">Carpetas de series</label>
                <textarea id="series_paths" v-model="library.series_paths" rows="3" class="input font-mono text-xs" :placeholder="library.default_series_path || '/home/fork_panel_xc/media/series'" />
              </div>
            </div>

            <p class="text-xs text-dark-500 dark:text-dark-400">
              Una ruta absoluta por línea. Si se dejan vacías se usan las carpetas por defecto del instalador
              (<code>media/movies</code>, <code>media/series</code>).
            </p>

            <div class="grid sm:grid-cols-4 gap-4">
              <div class="sm:col-span-2">
                <label for="extensions" class="label">Extensiones</label>
                <input id="extensions" v-model="library.extensions" class="input" placeholder="mkv,mp4,avi" />
              </div>
              <div>
                <label for="interval" class="label">Auto-scan (min)</label>
                <input id="interval" v-model.number="library.scan_interval_minutes" type="number" min="5" max="1440" class="input" />
              </div>
              <div>
                <label for="enrich_limit" class="label">Límite TMDB/scan</label>
                <input id="enrich_limit" v-model.number="library.enrich_limit" type="number" min="0" max="200" class="input" />
              </div>
            </div>

            <div class="flex flex-wrap gap-5 text-sm text-dark-700 dark:text-dark-300">
              <label class="flex items-center gap-2">
                <input v-model="library.auto_enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
                Escaneo automático
              </label>
              <label class="flex items-center gap-2">
                <input v-model="library.enrich_tmdb" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" :disabled="!library.tmdb_available" />
                Enriquecer con TMDB
              </label>
              <label class="flex items-center gap-2">
                <input v-model="library.mark_missing" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
                Marcar archivos borrados como missing
              </label>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-dark-200 dark:border-dark-700 pt-4">
              <div class="text-sm text-dark-600 dark:text-dark-400">
                <p>Último escaneo: <strong class="text-dark-900 dark:text-white">{{ formatDate(library.last_scan_at) }}</strong></p>
                <p v-if="library.stats" class="text-xs mt-0.5">
                  {{ library.stats.movies }} películas · {{ library.stats.episodes }} episodios · {{ library.stats.missing }} missing
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary" :disabled="scanning || busy" @click="saveLibrary">
                  {{ busy ? 'Saving…' : 'Save library settings' }}
                </button>
                <button type="button" class="btn-secondary" :disabled="scanning" @click="runScan(true)">
                  {{ scanning ? 'Working…' : 'Dry run' }}
                </button>
                <button type="button" class="btn-primary" :disabled="scanning" @click="runScan(false)">
                  <ArrowPathIcon v-if="scanning" class="w-4 h-4 animate-spin" />
                  <FolderOpenIcon v-else class="w-4 h-4" />
                  {{ scanning ? 'Scanning…' : 'Scan now' }}
                </button>
              </div>
            </div>

            <div v-if="library.last_report" class="rounded-lg border border-dark-200 dark:border-dark-700 p-4 text-sm space-y-1">
              <p class="font-medium text-dark-900 dark:text-white">
                Último resultado {{ library.last_report.dry_run ? '(dry run)' : '' }}
              </p>
              <p class="text-dark-600 dark:text-dark-400">
                {{ library.last_report.files_seen }} archivos vistos ·
                +{{ library.last_report.movies_added }} películas ·
                +{{ library.last_report.episodes_added }} episodios ·
                {{ library.last_report.skipped }} sin cambios ·
                {{ library.last_report.ignored }} ignorados ·
                {{ library.last_report.missing }} missing
              </p>
              <p v-if="library.last_report.errors?.length" class="text-red-600 dark:text-red-400 text-xs">
                {{ library.last_report.errors.slice(0, 3).join(' · ') }}
              </p>
              <p v-if="!library.tmdb_available" class="text-xs text-amber-600 dark:text-amber-400">
                TMDB no está configurado: se indexará sin póster/sinopsis.
              </p>
            </div>
          </template>
        </template>
      </div>
    </div>

    <div class="card">
      <div class="card-header flex items-center justify-between">
        <h3 class="font-semibold text-dark-900 dark:text-white">Installed modules</h3>
        <router-link to="/modules" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">Manage all</router-link>
      </div>
      <div class="card-body">
        <p v-if="loading" class="text-sm text-dark-400">Loading...</p>
        <p v-else-if="modules.filter(m => !m.available).length === 0" class="text-sm text-dark-500 dark:text-dark-400">No modules installed.</p>
        <ul v-else class="divide-y divide-dark-100 dark:divide-dark-800 text-sm">
          <li v-for="module in modules.filter(m => !m.available)" :key="module.id" class="py-3 flex items-center justify-between gap-3">
            <div>
              <p class="font-medium text-dark-900 dark:text-white">{{ module.name }} <span class="text-xs text-dark-400">v{{ module.version }}</span></p>
              <p class="text-dark-500 dark:text-dark-400">{{ module.description }}</p>
            </div>
            <span class="badge" :class="module.status === 'enabled' ? 'badge-success' : 'badge-warning'">{{ module.status }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
