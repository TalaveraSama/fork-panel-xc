import axios from 'axios'
import { useAuthStore } from '@stores/auth'
import { useUiStore } from '@stores/ui'

// Create axios instance
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const authStore = useAuthStore()
    const uiStore = useUiStore()
    const originalRequest = error.config

    // Handle 401 Unauthorized
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true

      // Try to refresh token
      const refreshed = await authStore.refreshAccessToken()
      if (refreshed) {
        // Retry original request with new token
        originalRequest.headers.Authorization = `Bearer ${authStore.token}`
        return api(originalRequest)
      } else {
        // Refresh failed, redirect to login
        authStore.logout()
        if (window.location.pathname !== '/login') {
          window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname)}`
        }
        return Promise.reject(error)
      }
    }

    // Handle 403 Forbidden
    if (error.response?.status === 403) {
      uiStore.toastError('You do not have permission to perform this action', 'Access Denied')
    }

    // Handle 422 Validation Error
    if (error.response?.status === 422) {
      const errors = error.response.data.errors || {}
      const firstError = Object.values(errors)[0]
      if (Array.isArray(firstError)) {
        uiStore.toastError(firstError[0], 'Validation Error')
      } else if (firstError) {
        uiStore.toastError(firstError, 'Validation Error')
      }
    }

    // Handle 500 Server Error
    if (error.response?.status >= 500) {
      uiStore.toastError('An unexpected error occurred. Please try again.', 'Server Error')
    }

    // Handle network errors
    if (!error.response) {
      uiStore.toastError('Network error. Please check your connection.', 'Connection Error')
    }

    return Promise.reject(error)
  }
)

export default api