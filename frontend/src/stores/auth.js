import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@services/api'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const token = ref(localStorage.getItem('fp_token') || null)
  const refreshToken = ref(localStorage.getItem('fp_refresh_token') || null)
  const initialized = ref(false)
  const permissions = ref([])
  const roles = ref([])
  const twoFactorRequired = ref(false)
  const twoFactorUserId = ref(null)

  // Computed
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => roles.value.includes('admin'))
  const isReseller = computed(() => roles.value.includes('reseller'))

  // Actions
  async function initAuth() {
    if (initialized.value) return

    const storedToken = localStorage.getItem('fp_token')
    if (storedToken) {
      token.value = storedToken
      try {
        await fetchUser()
      } catch (error) {
        // Token invalid, clear auth
        logout()
      }
    }
    initialized.value = true
  }

  async function login(credentials) {
    const { data } = await api.post('/auth/login', credentials)

    if (data.two_factor_required) {
      twoFactorRequired.value = true
      twoFactorUserId.value = data.user_id
      return { twoFactorRequired: true }
    }

    setAuthData(data)
    return { success: true }
  }

  async function verifyTwoFactor(code) {
    const { data } = await api.post('/auth/2fa/verify', {
      user_id: twoFactorUserId.value,
      code,
    })

    setAuthData(data)
    twoFactorRequired.value = false
    twoFactorUserId.value = null
    return { success: true }
  }

  async function fetchUser() {
    const { data } = await api.get('/auth/me')
    user.value = data.user
    permissions.value = data.permissions || []
    roles.value = data.roles || []
  }

  function setAuthData(data) {
    token.value = data.access_token
    refreshToken.value = data.refresh_token
    user.value = data.user
    permissions.value = data.permissions || []
    roles.value = data.roles || []

    localStorage.setItem('fp_token', data.access_token)
    localStorage.setItem('fp_refresh_token', data.refresh_token)

    // Set default authorization header
    api.defaults.headers.common['Authorization'] = `Bearer ${data.access_token}`
  }

  async function refreshAccessToken() {
    if (!refreshToken.value) {
      logout()
      return false
    }

    try {
      const { data } = await api.post('/auth/refresh', {
        refresh_token: refreshToken.value,
      })

      token.value = data.access_token
      localStorage.setItem('fp_token', data.access_token)
      api.defaults.headers.common['Authorization'] = `Bearer ${data.access_token}`
      return true
    } catch (error) {
      logout()
      return false
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch (error) {
      // Ignore logout errors
    } finally {
      clearAuth()
    }
  }

  function clearAuth() {
    user.value = null
    token.value = null
    refreshToken.value = null
    permissions.value = []
    roles.value = []
    twoFactorRequired.value = false
    twoFactorUserId.value = null

    localStorage.removeItem('fp_token')
    localStorage.removeItem('fp_refresh_token')

    delete api.defaults.headers.common['Authorization']
  }

  function hasPermission(permission) {
    if (isAdmin.value) return true
    if (Array.isArray(permission)) {
      return permission.some(p => permissions.value.includes(p))
    }
    return permissions.value.includes(permission)
  }

  function hasPermissions(permissionsList) {
    if (isAdmin.value) return true
    return permissionsList.every(p => permissions.value.includes(p))
  }

  function hasRole(role) {
    if (Array.isArray(role)) {
      return role.some(r => roles.value.includes(r))
    }
    return roles.value.includes(role)
  }

  async function updateProfile(profileData) {
    const { data } = await api.put('/auth/profile', profileData)
    user.value = { ...user.value, ...data.user }
    return data
  }

  async function changePassword(passwords) {
    await api.put('/auth/password', passwords)
  }

  return {
    // State
    user,
    token,
    refreshToken,
    initialized,
    permissions,
    roles,
    twoFactorRequired,
    twoFactorUserId,

    // Computed
    isAuthenticated,
    isAdmin,
    isReseller,

    // Actions
    initAuth,
    login,
    verifyTwoFactor,
    fetchUser,
    refreshAccessToken,
    logout,
    hasPermission,
    hasPermissions,
    hasRole,
    updateProfile,
    changePassword,
  }
})