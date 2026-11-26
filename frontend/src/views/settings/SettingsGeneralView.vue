<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import settingsService from '@services/settings'

const uiStore = useUiStore()
const form = reactive({ app_name: 'Fork Panel XC', app_url: '', default_language: 'en', timezone: 'UTC' })
const loading = ref(true)
const saving = ref(false)

onMounted(async () => {
  try {
    const res = await settingsService.section('general')
    Object.assign(form, res.data || res)
  } catch (error) {
    uiStore.toastError('Failed to load settings')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  try {
    await settingsService.updateSection('general', form)
    uiStore.toastSuccess('Settings saved')
  } catch (error) {
    uiStore.toastError('Failed to save settings')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="save" class="card">
    <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">General</h3></div>
    <div class="card-body space-y-5">
      <div>
        <label for="app_name" class="label">Panel name</label>
        <input id="app_name" v-model="form.app_name" class="input" />
      </div>
      <div>
        <label for="app_url" class="label">Panel URL</label>
        <input id="app_url" v-model="form.app_url" type="url" class="input" placeholder="https://panel.example.com" />
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label for="default_language" class="label">Default language</label>
          <select id="default_language" v-model="form.default_language" class="input">
            <option value="en">English</option>
            <option value="es">Español</option>
            <option value="ru">Русский</option>
          </select>
        </div>
        <div>
          <label for="timezone" class="label">Timezone</label>
          <input id="timezone" v-model="form.timezone" class="input" placeholder="UTC" />
        </div>
      </div>
    </div>
    <div class="card-footer flex justify-end">
      <button type="submit" :disabled="saving || loading" class="btn-primary">{{ saving ? 'Saving…' : 'Save changes' }}</button>
    </div>
  </form>
</template>
