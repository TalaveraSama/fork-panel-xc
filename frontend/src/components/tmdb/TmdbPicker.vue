<script setup>
import { ref, watch } from 'vue'
import { useUiStore } from '@stores/ui'
import tmdbService from '@services/tmdb'
import { MagnifyingGlassIcon, StarIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  type: { type: String, default: 'movie' }, // movie | tv
})

const emit = defineEmits(['select'])

const uiStore = useUiStore()
const query = ref('')
const year = ref('')
const results = ref([])
const loading = ref(false)
const selecting = ref(null)
const error = ref('')

watch(() => props.type, () => {
  results.value = []
  error.value = ''
})

async function search() {
  if (query.value.trim().length < 2) {
    error.value = 'Escribe al menos 2 caracteres.'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const response = await tmdbService.search(query.value.trim(), props.type, year.value || null)
    results.value = response.results || []

    if (results.value.length === 0) {
      error.value = 'Sin resultados en TMDB.'
    }
  } catch (exception) {
    const status = exception.response?.status
    error.value = exception.response?.data?.error
      || (status === 404 ? 'El módulo TMDB no está instalado.' : 'No se pudo consultar TMDB.')
  } finally {
    loading.value = false
  }
}

async function choose(item) {
  selecting.value = item.id

  try {
    const response = await tmdbService.details(props.type, item.id)
    emit('select', response.data || response)
    uiStore.toastSuccess(`Datos de "${item.title}" cargados`)
  } catch (exception) {
    emit('select', item)
    uiStore.toastWarning('Se cargaron los datos básicos; no se pudo obtener el detalle.')
  } finally {
    selecting.value = null
  }
}
</script>

<template>
  <div class="card">
    <div class="card-header flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold text-dark-900 dark:text-white">Importar desde TMDB</h3>
        <p class="text-xs text-dark-500 dark:text-dark-400 mt-0.5">
          Busca el título y rellena póster, backdrop, sinopsis, año, duración y géneros.
        </p>
      </div>
      <span class="badge badge-info">TMDB</span>
    </div>

    <div class="card-body space-y-4">
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-dark-400" />
          <input
            v-model="query"
            type="text"
            class="input pl-10"
            :placeholder="type === 'tv' ? 'Buscar serie…' : 'Buscar película…'"
            @keyup.enter="search"
          />
        </div>
        <input v-model="year" type="number" min="1900" max="2100" class="input sm:w-32" placeholder="Año" />
        <button type="button" class="btn-primary" :disabled="loading" @click="search">
          <ArrowPathIcon v-if="loading" class="w-4 h-4 animate-spin" />
          <MagnifyingGlassIcon v-else class="w-4 h-4" />
          Buscar
        </button>
      </div>

      <p v-if="error" class="text-sm text-amber-600 dark:text-amber-400">{{ error }}</p>

      <div v-if="results.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 max-h-[28rem] overflow-y-auto pr-1">
        <button
          v-for="item in results"
          :key="item.id"
          type="button"
          class="text-left rounded-lg border border-dark-200 dark:border-dark-700 hover:border-primary-400 dark:hover:border-primary-600 transition-colors overflow-hidden bg-white dark:bg-dark-900"
          :disabled="selecting === item.id"
          @click="choose(item)"
        >
          <div class="aspect-[2/3] bg-dark-100 dark:bg-dark-800 overflow-hidden">
            <img v-if="item.poster_url" :src="item.poster_url" :alt="item.title" class="w-full h-full object-cover" loading="lazy" />
            <div v-else class="w-full h-full flex items-center justify-center text-xs text-dark-400">Sin póster</div>
          </div>
          <div class="p-2.5">
            <p class="text-sm font-medium text-dark-900 dark:text-white line-clamp-2">{{ item.title }}</p>
            <p class="text-xs text-dark-500 dark:text-dark-400 mt-0.5">
              {{ item.year || '—' }}
              <span v-if="item.rating" class="inline-flex items-center gap-0.5 ml-1">
                <StarIcon class="w-3 h-3 text-amber-500" />{{ item.rating }}
              </span>
            </p>
            <p v-if="selecting === item.id" class="text-xs text-primary-600 mt-1">Cargando detalle…</p>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
