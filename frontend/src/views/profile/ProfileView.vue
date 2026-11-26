<script setup>
import { reactive, ref } from 'vue'
import { useAuthStore } from '@stores/auth'
import { useUiStore } from '@stores/ui'

const authStore = useAuthStore()
const uiStore = useUiStore()

const profile = reactive({
  email: authStore.user?.email || '',
  full_name: authStore.user?.full_name || '',
  timezone: authStore.user?.timezone || 'UTC',
  language: authStore.user?.language || 'en',
})

const passwords = reactive({
  current_password: '',
  new_password: '',
  confirm_password: '',
})

const savingProfile = ref(false)
const savingPassword = ref(false)

async function saveProfile() {
  savingProfile.value = true
  try {
    await authStore.updateProfile(profile)
    uiStore.toastSuccess('Profile updated')
  } catch (error) {
    uiStore.toastError('Failed to update profile')
  } finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  if (passwords.new_password !== passwords.confirm_password) {
    uiStore.toastError('Passwords do not match')
    return
  }
  savingPassword.value = true
  try {
    await authStore.changePassword({
      current_password: passwords.current_password,
      new_password: passwords.new_password,
    })
    uiStore.toastSuccess('Password changed')
    passwords.current_password = ''
    passwords.new_password = ''
    passwords.confirm_password = ''
  } catch (error) {
    uiStore.toastError('Failed to change password')
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Profile</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Manage your account details and password</p>
    </div>

    <form @submit.prevent="saveProfile" class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Account</h3></div>
      <div class="card-body grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="label">Username</label>
          <input :value="authStore.user?.username" class="input" disabled />
        </div>
        <div>
          <label for="email" class="label">Email</label>
          <input id="email" v-model="profile.email" type="email" class="input" />
        </div>
        <div>
          <label for="full_name" class="label">Full name</label>
          <input id="full_name" v-model="profile.full_name" class="input" />
        </div>
        <div>
          <label for="timezone" class="label">Timezone</label>
          <input id="timezone" v-model="profile.timezone" class="input" placeholder="UTC" />
        </div>
      </div>
      <div class="card-footer flex justify-end">
        <button type="submit" :disabled="savingProfile" class="btn-primary">{{ savingProfile ? 'Saving…' : 'Save profile' }}</button>
      </div>
    </form>

    <form @submit.prevent="savePassword" class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Change password</h3></div>
      <div class="card-body grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label for="current" class="label">Current password</label>
          <input id="current" v-model="passwords.current_password" type="password" class="input" required />
        </div>
        <div>
          <label for="new" class="label">New password</label>
          <input id="new" v-model="passwords.new_password" type="password" minlength="8" class="input" required />
        </div>
        <div>
          <label for="confirm" class="label">Confirm password</label>
          <input id="confirm" v-model="passwords.confirm_password" type="password" minlength="8" class="input" required />
        </div>
      </div>
      <div class="card-footer flex justify-end">
        <button type="submit" :disabled="savingPassword" class="btn-primary">{{ savingPassword ? 'Saving…' : 'Change password' }}</button>
      </div>
    </form>
  </div>
</template>
