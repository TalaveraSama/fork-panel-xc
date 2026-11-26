<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import categoriesService from '@services/categories'
import { PlusIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const categories = ref([])
const loading = ref(false)
const showForm = ref(false)
const saving = ref(false)
const form = reactive({ name: '', type: 'live' })

async function fetchCategories() {
  loading.value = true
  try {
    const res = await categoriesService.list()
    categories.value = res.data || []
  } catch (error) {
    uiStore.toastError('Failed to load categories')
  } finally {
    loading.value = false
  }
}

async function createCategory() {
  saving.value = true
  try {
    await categoriesService.create(form)
    uiStore.toastSuccess('Category created')
    showForm.value = false
    form.name = ''
    await fetchCategories()
  } catch (error) {
    uiStore.toastError('Failed to create category')
  } finally {
    saving.value = false
  }
}

async function removeCategory(id) {
  if (!window.confirm('Delete this category?')) return
  try {
    await categoriesService.delete(id)
    uiStore.toastSuccess('Category deleted')
    await fetchCategories()
  } catch (error) {
    uiStore.toastError('Failed to delete category')
  }
}

onMounted(fetchCategories)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Categories</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Organize streams, VOD and series</p>
      </div>
      <button @click="showForm = !showForm" class="btn-primary"><PlusIcon class="w-5 h-5" /> Add Category</button>
    </div>

    <form v-if="showForm" @submit.prevent="createCategory" class="card">
      <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label for="name" class="label">Name</label>
          <input id="name" v-model="form.name" class="input" required />
        </div>
        <div>
          <label for="type" class="label">Type</label>
          <select id="type" v-model="form.type" class="input">
            <option value="live">Live</option>
            <option value="vod">VOD</option>
            <option value="series">Series</option>
            <option value="radio">Radio</option>
          </select>
        </div>
      </div>
      <div class="card-footer flex justify-end gap-3">
        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
        <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Saving…' : 'Create Category' }}</button>
      </div>
    </form>

    <div class="card">
      <div class="table-container border-0 rounded-none">
        <table class="table">
          <thead><tr><th class="w-24">ID</th><th>Name</th><th class="w-32">Type</th><th class="w-28"></th></tr></thead>
          <tbody>
            <tr v-for="category in categories" :key="category.id">
              <td>{{ category.id }}</td>
              <td class="font-medium text-dark-900 dark:text-white">{{ category.name }}</td>
              <td><span class="badge badge-info">{{ category.type }}</span></td>
              <td><button @click="removeCategory(category.id)" class="btn-danger btn-sm">Delete</button></td>
            </tr>
            <tr v-if="!loading && categories.length === 0"><td colspan="4" class="text-center py-10 text-dark-400">No categories yet</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
