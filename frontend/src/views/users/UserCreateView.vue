<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import usersService from '@services/users'

const router = useRouter()
const uiStore = useUiStore()
const saving = ref(false)

const form = reactive({
  username: '',
  email: '',
  password: '',
  is_reseller: false,
  enabled: true,
})

async function submit() {
  saving.value = true
  try {
    await usersService.create(form)
    uiStore.toastSuccess('User created')
    router.push({ name: 'users' })
  } catch (error) {
    uiStore.toastError('Failed to create user')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Create User</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Add a new panel user or reseller</p>
    </div>

    <form @submit.prevent="submit" class="card">
      <div class="card-body space-y-5">
        <div>
          <label for="username" class="label">Username <span class="text-red-500">*</span></label>
          <input id="username" v-model="form.username" class="input" required />
        </div>
        <div>
          <label for="email" class="label">Email</label>
          <input id="email" v-model="form.email" type="email" class="input" />
        </div>
        <div>
          <label for="password" class="label">Password <span class="text-red-500">*</span></label>
          <input id="password" v-model="form.password" type="password" class="input" minlength="8" required />
        </div>
        <div class="space-y-2">
          <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
            <input v-model="form.is_reseller" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
            Reseller account
          </label>
          <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
            <input v-model="form.enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
            Enabled
          </label>
        </div>
      </div>
      <div class="card-footer flex justify-end gap-3">
        <router-link to="/users" class="btn-secondary">Cancel</router-link>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Creating…' : 'Create User' }}</button>
      </div>
    </form>
  </div>
</template>
