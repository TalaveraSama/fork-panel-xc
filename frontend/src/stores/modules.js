import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@services/api'

export const useModulesStore = defineStore('modules', () => {
  // State
  const modules = ref([])
  const installedModules = ref([])
  const enabledModules = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Computed
  const availableModules = computed(() => modules.value.filter(m => !installedModules.value.includes(m.id)))
  const modulesWithUpdates = computed(() => installedModules.value.filter(m => m.update_available))

  // Actions
  async function fetchModules() {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/modules')
      modules.value = data.modules || []
      installedModules.value = data.installed || []
      enabledModules.value = data.enabled || []
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function installModule(moduleId) {
    loading.value = true
    try {
      const { data } = await api.post(`/modules/${moduleId}/install`)
      await fetchModules()
      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateModule(moduleId) {
    loading.value = true
    try {
      const { data } = await api.post(`/modules/${moduleId}/update`)
      await fetchModules()
      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function uninstallModule(moduleId) {
    loading.value = true
    try {
      const { data } = await api.post(`/modules/${moduleId}/uninstall`)
      await fetchModules()
      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function enableModule(moduleId) {
    loading.value = true
    try {
      const { data } = await api.post(`/modules/${moduleId}/enable`)
      await fetchModules()
      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function disableModule(moduleId) {
    loading.value = true
    try {
      const { data } = await api.post(`/modules/${moduleId}/disable`)
      await fetchModules()
      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getModule(moduleId) {
    try {
      const { data } = await api.get(`/modules/${moduleId}`)
      return data
    } catch (err) {
      error.value = err.message
      throw err
    }
  }

  async function getModuleSettings(moduleId) {
    try {
      const { data } = await api.get(`/modules/${moduleId}/settings`)
      return data
    } catch (err) {
      error.value = err.message
      throw err
    }
  }

  async function updateModuleSettings(moduleId, settings) {
    try {
      const { data } = await api.put(`/modules/${moduleId}/settings`, settings)
      return data
    } catch (err) {
      error.value = err.message
      throw err
    }
  }

  function isInstalled(moduleId) {
    return installedModules.value.some(m => m.id === moduleId)
  }

  function isEnabled(moduleId) {
    return enabledModules.value.some(m => m.id === moduleId)
  }

  function getModuleById(moduleId) {
    return modules.value.find(m => m.id === moduleId)
  }

  return {
    // State
    modules,
    installedModules,
    enabledModules,
    loading,
    error,

    // Computed
    availableModules,
    modulesWithUpdates,

    // Actions
    fetchModules,
    installModule,
    updateModule,
    uninstallModule,
    enableModule,
    disableModule,
    getModule,
    getModuleSettings,
    updateModuleSettings,
    isInstalled,
    isEnabled,
    getModuleById,
  }
})