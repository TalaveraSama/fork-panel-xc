<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import resellersService from '@services/resellers'

const router = useRouter()
const uiStore = useUiStore()
const saving = ref(false)

const form = reactive({
  username: '',
  email: '',
  password: '',
  credits: 0,
  enabled: true,
})

async function submit() {
  saving.value = true
  try {
    await resellersService.create(form)
    uiStore.toastSuccess('Reseller created')
    router.push({ name: 'resellers' })
  } catch (error) {
    uiStore.toastError('Failed to create reseller')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Add Reseller</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Create a reseller account with credits</p>
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
        <div>
          <label for="credits" class="label">Initial credits</label>
          <input id="credits" v-model.number="form.credits" type="number" min="0" class="input" />
        </div>
        <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
          <input v-model="form.enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
          Enabled
        </label>
      </div>
      <div class="card-footer flex justify-end gap-3">
        <router-link to="/resellers" class="btn-secondary">Cancel</router-link>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Creating…' : 'Create Reseller' }}</button>
      </div>
    </form>
  </div>
</template>
