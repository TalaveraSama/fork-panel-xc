<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useUiStore } from '@stores/ui'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const uiStore = useUiStore()

const form = reactive({
  username: '',
  password: '',
  remember: false,
})

const errors = reactive({})
const loading = ref(false)

async function handleSubmit() {
  errors.value = {}
  loading.value = true
  uiStore.setLoading(true, 'Signing in...')

  try {
    const result = await authStore.login(form)

    if (result.twoFactorRequired) {
      router.push({ name: '2fa' })
    } else {
      uiStore.toastSuccess('Welcome back!')
      const redirect = route.query.redirect || '/'
      router.push(redirect)
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else if (error.response?.data?.message) {
      uiStore.toastError(error.response.data.message)
    } else {
      uiStore.toastError('Invalid credentials')
    }
  } finally {
    loading.value = false
    uiStore.setLoading(false)
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div>
      <label for="username" class="label">Username</label>
      <input
        id="username"
        v-model="form.username"
        type="text"
        autocomplete="username"
        class="input"
        :class="{ 'input-error': errors.username }"
        placeholder="Enter your username"
        :disabled="loading"
        required
      />
      <p v-if="errors.username" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.username[0] }}
      </p>
    </div>

    <div>
      <label for="password" class="label">Password</label>
      <input
        id="password"
        v-model="form.password"
        type="password"
        autocomplete="current-password"
        class="input"
        :class="{ 'input-error': errors.password }"
        placeholder="Enter your password"
        :disabled="loading"
        required
      />
      <p v-if="errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.password[0] }}
      </p>
    </div>

    <div class="flex items-center justify-between">
      <label class="flex items-center gap-2 cursor-pointer">
        <input
          v-model="form.remember"
          type="checkbox"
          class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500"
        />
        <span class="text-sm text-dark-600 dark:text-dark-400">Remember me</span>
      </label>
      <router-link to="/forgot-password" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
        Forgot password?
      </router-link>
    </div>

    <button
      type="submit"
      :disabled="loading"
      class="btn-primary w-full btn-lg"
    >
      <span v-if="loading" class="flex items-center gap-2">
        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        Signing in...
      </span>
      <span v-else>Sign In</span>
    </button>

    <div class="text-center text-sm text-dark-500 dark:text-dark-400">
      <p>Demo credentials: <code class="bg-dark-100 dark:bg-dark-800 px-1.5 py-0.5 rounded">admin</code> / <code class="bg-dark-100 dark:bg-dark-800 px-1.5 py-0.5 rounded">admin</code></p>
    </div>
  </form>
</template>