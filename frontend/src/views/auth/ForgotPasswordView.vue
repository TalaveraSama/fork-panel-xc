<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import api from '@services/api'

const router = useRouter()
const uiStore = useUiStore()
const email = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  try {
    await api.post('/auth/forgot-password', { email: email.value })
    uiStore.toastSuccess('If the account exists, a reset link was sent')
    router.push({ name: 'login' })
  } catch (error) {
    uiStore.toastError('Unable to send reset link')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-5">
    <div>
      <h2 class="text-xl font-semibold text-dark-900 dark:text-white">Reset your password</h2>
      <p class="text-sm text-dark-500 dark:text-dark-400 mt-1">We will email you a link to reset your password.</p>
    </div>

    <div>
      <label for="email" class="label">Email address</label>
      <input id="email" v-model="email" type="email" class="input" placeholder="you@example.com" required />
    </div>

    <button type="submit" :disabled="loading" class="btn-primary w-full">Send reset link</button>

    <router-link to="/login" class="block text-center text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
      Back to sign in
    </router-link>
  </form>
</template>
