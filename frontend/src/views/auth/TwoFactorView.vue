<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useUiStore } from '@stores/ui'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

const code = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  try {
    await authStore.verifyTwoFactor(code.value)
    uiStore.toastSuccess('Two-factor verification complete')
    router.push('/')
  } catch (error) {
    uiStore.toastError('Invalid verification code')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-5">
    <div>
      <h2 class="text-xl font-semibold text-dark-900 dark:text-white">Two-factor verification</h2>
      <p class="text-sm text-dark-500 dark:text-dark-400 mt-1">Enter the 6-digit code from your authenticator app.</p>
    </div>

    <div>
      <label for="code" class="label">Verification code</label>
      <input id="code" v-model="code" inputmode="numeric" maxlength="6" class="input text-center tracking-[0.5em] text-lg" placeholder="000000" required />
    </div>

    <button type="submit" :disabled="loading" class="btn-primary w-full">Verify</button>

    <router-link to="/login" class="block text-center text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
      Back to sign in
    </router-link>
  </form>
</template>
