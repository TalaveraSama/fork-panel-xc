import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useUiStore = defineStore('ui', () => {
  // State
  const sidebarOpen = ref(false)
  const sidebarCollapsed = ref(false)
  const darkMode = ref(false)
  const loading = ref(false)
  const loadingText = ref('')
  const hasUnsavedChanges = ref(false)
  const toasts = ref([])
  const modals = ref([])
  const activeTab = ref({})
  const tableFilters = ref({})
  const tablePagination = ref({})
  const tableSort = ref({})

  // Computed
  const isMobile = computed(() => window.innerWidth < 1024)

  // Actions
  function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
  }

  function closeSidebar() {
    sidebarOpen.value = false
  }

  function openSidebar() {
    sidebarOpen.value = true
  }

  function toggleSidebarCollapsed() {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem('fp_sidebar_collapsed', sidebarCollapsed.value.toString())
  }

  function initSidebarCollapsed() {
    const stored = localStorage.getItem('fp_sidebar_collapsed')
    if (stored !== null) {
      sidebarCollapsed.value = stored === 'true'
    }
  }

  function toggleDarkMode() {
    darkMode.value = !darkMode.value
    localStorage.setItem('fp_dark_mode', darkMode.value.toString())
    applyDarkMode()
  }

  function initDarkMode() {
    const stored = localStorage.getItem('fp_dark_mode')
    if (stored !== null) {
      darkMode.value = stored === 'true'
    } else {
      // Check system preference
      darkMode.value = window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    applyDarkMode()
  }

  function applyDarkMode() {
    if (darkMode.value) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }

  function setLoading(show, text = '') {
    loading.value = show
    loadingText.value = text
  }

  function setUnsavedChanges(hasChanges) {
    hasUnsavedChanges.value = hasChanges
  }

  // Toast notifications
  function addToast(toast) {
    const id = Date.now() + Math.random()
    const newToast = {
      id,
      type: 'info',
      title: '',
      message: '',
      duration: 5000,
      ...toast,
    }
    toasts.value.push(newToast)

    if (newToast.duration > 0) {
      setTimeout(() => removeToast(id), newToast.duration)
    }

    return id
  }

  function removeToast(id) {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  function clearToasts() {
    toasts.value = []
  }

  // Convenience toast methods
  function toastSuccess(message, title = 'Success') {
    return addToast({ type: 'success', title, message })
  }

  function toastError(message, title = 'Error') {
    return addToast({ type: 'error', title, message, duration: 8000 })
  }

  function toastInfo(message, title = 'Info') {
    return addToast({ type: 'info', title, message })
  }

  function toastWarning(message, title = 'Warning') {
    return addToast({ type: 'warning', title, message })
  }

  // Modals
  function openModal(modal) {
    const id = modal.id || `modal_${Date.now()}`
    modals.value.push({ ...modal, id })
    return id
  }

  function closeModal(id) {
    const index = modals.value.findIndex(m => m.id === id)
    if (index !== -1) {
      modals.value.splice(index, 1)
    }
  }

  function closeAllModals() {
    modals.value = []
  }

  // Table state management
  function setTableFilter(key, filters) {
    tableFilters.value[key] = filters
  }

  function getTableFilter(key) {
    return tableFilters.value[key] || {}
  }

  function clearTableFilter(key) {
    delete tableFilters.value[key]
  }

  function setTablePagination(key, pagination) {
    tablePagination.value[key] = pagination
  }

  function getTablePagination(key) {
    return tablePagination.value[key] || { page: 1, perPage: 25, total: 0 }
  }

  function setTableSort(key, sort) {
    tableSort.value[key] = sort
  }

  function getTableSort(key) {
    return tableSort.value[key] || { field: 'id', direction: 'desc' }
  }

  // Tab management
  function setActiveTab(key, tab) {
    activeTab.value[key] = tab
  }

  function getActiveTab(key) {
    return activeTab.value[key] || 0
  }

  // Initialize on store creation
  initDarkMode()
  initSidebarCollapsed()

  return {
    // State
    sidebarOpen,
    sidebarCollapsed,
    darkMode,
    loading,
    loadingText,
    hasUnsavedChanges,
    toasts,
    modals,
    activeTab,
    tableFilters,
    tablePagination,
    tableSort,

    // Computed
    isMobile,

    // Actions
    toggleSidebar,
    closeSidebar,
    openSidebar,
    toggleSidebarCollapsed,
    initSidebarCollapsed,
    toggleDarkMode,
    initDarkMode,
    applyDarkMode,
    setLoading,
    setUnsavedChanges,
    addToast,
    removeToast,
    clearToasts,
    toastSuccess,
    toastError,
    toastInfo,
    toastWarning,
    openModal,
    closeModal,
    closeAllModals,
    setTableFilter,
    getTableFilter,
    clearTableFilter,
    setTablePagination,
    getTablePagination,
    setTableSort,
    getTableSort,
    setActiveTab,
    getActiveTab,
  }
})