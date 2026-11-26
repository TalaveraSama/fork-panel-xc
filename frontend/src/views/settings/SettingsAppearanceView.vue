<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import settingsService from '@services/settings'

const uiStore = useUiStore()
const form = reactive({ dark_mode_default: false, brand_color: '#ea580c', logo_url: '', footer_text: '' })
const loading = ref(true)
const saving = ref(false)

onMounted(async () => {
  try {
    const res = await settingsService.section('appearance')
    Object.assign(form, res.data || res)
  } catch (error) {
    uiStore.toastError('Failed to load appearance settings')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  try {
    await settingsService.updateSection('appearance', form)
    uiStore.toastSuccess('Appearance settings saved')
  } catch (error) {
    uiStore.toastError('Failed to save appearance settings')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="save" class="card">
    <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Appearance</h3></div>
    <div class="card-body space-y-5">
      <div>
        <label for="brand_color" class="label">Brand color</label>
        <div class="flex items-center gap-3">
          <input id="brand_color" v-model="form.brand_color" type="color" class="h-10 w-16 rounded border border-dark-300 bg-transparent" />
          <input v-model="form.brand_color" class="input max-w-[12rem]" />
        </div>
      </div>
      <div>
        <label for="logo_url" class="label">Logo URL</label>
        <input id="logo_url" v-model="form.logo_url" type="url" class="input" placeholder="https://…/logo.svg" />
      </div>
      <div>
        <label for="footer_text" class="label">Footer text</label>
        <input id="footer_text" v-model="form.footer_text" class="input" placeholder="Fork Panel XC — Forked from XC_VM by Vateron Media · AGPL-3.0" />
      </div>
      <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
        <input v-model="form.dark_mode_default" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
        Use dark mode by default
      </label>
    </div>
    <div class="card-footer flex justify-end">
      <button type="submit" :disabled="saving || loading" class="btn-primary">{{ saving ? 'Saving…' : 'Save changes' }}</button>
    </div>
  </form>
</template>
