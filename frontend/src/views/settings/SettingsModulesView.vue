<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useUiStore } from '@stores/ui'
import modulesService from '@services/modules'
import tmdbService from '@services/tmdb'
import { CheckCircleIcon, KeyIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

const modules = ref([])
const loading = ref(true)
const busy = ref(false)
const tmdbSettings = ref(null)
const tmdbForm = reactive({ api_key: '', language: 'es-ES' })

const tmdbInstalled = computed(() => modules.value.some(m => m.id === 'tmdb'))
const tmdbEnabled = computed(() => modules.value.some(m => m.id === 'tmdb' && m.status === 'enabled'))
const tmdbAvailable = computed(() => modules.value.some(m => m.id === 'tmdb' && m.available))

async function load() {
  loading.value = true
  try {
    const response = await modulesService.list()
    const installed = (response.installed || []).map(m => ({ ...m, available: false }))
    const available = (response.available || []).map(m => ({
      id: m.id,
      name: m.name,
      version: m.version,
      description: m.description,
      status: 'available',
      available: true,
    }))
    modules.value = [...installed, ...available]

    if (tmdbInstalled.value) {
      await loadTmdbSettings()
    }
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

async function installTmdb() {
  busy.value = true
  try {
    await modulesService.install('tmdb')
    if (modulesService.enable) {
      try { await modulesService.enable('tmdb') } catch { /* already enabled */ }
    }
    uiStore.toastSuccess('TMDB module installed')
    await load()
  } catch (error) {
    uiStore.toastError(error.response?.data?.error || 'Failed to install TMDB module')
  } finally {
    busy.value = false
  }
}

async function saveTmdb() {
  busy.value = true
  try {
    const payload = { language: tmdbForm.language }
    if (tmdbForm.api_key.trim() !== '') {
      payload.api_key = tmdbForm.api_key.trim()
    }
    tmdbSettings.value = await tmdbService.saveSettings(payload)
    tmdbForm.api_key = ''
    uiStore.toastSuccess('TMDB settings saved')
  } catch (error) {
    uiStore.toastError(error.response?.data?.error || 'TMDB rejected the settings')
  } finally {
    busy.value = false
  }
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
        <span class="badge" :class="tmdbEnabled ? 'badge-success' : 'badge-warning'">
          {{ tmdbEnabled ? 'enabled' : (tmdbInstalled ? 'installed' : 'not installed') }}
        </span>
      </div>

      <div class="card-body space-y-4">
        <p v-if="loading" class="text-sm text-dark-400">Loading…</p>

        <template v-else>
          <div v-if="!tmdbInstalled" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="text-sm text-dark-600 dark:text-dark-400">
              Instala el módulo para importar metadatos desde themoviedb.org.
            </p>
            <button class="btn-primary" :disabled="busy || !tmdbAvailable" @click="installTmdb">
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
                  <input
                    id="tmdb_key"
                    v-model="tmdbForm.api_key"
                    type="password"
                    class="input pl-10"
                    placeholder="Pega aquí tu v3 API key"
                    autocomplete="off"
                  />
                </div>
                <p class="text-xs text-dark-500 dark:text-dark-400 mt-1">
                  Se valida contra TMDB antes de guardar. También puedes usar TMDB_API_KEY en config/.env.
                </p>
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
              <button class="btn-primary" :disabled="busy" @click="saveTmdb">
                {{ busy ? 'Saving…' : 'Save TMDB settings' }}
              </button>
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
