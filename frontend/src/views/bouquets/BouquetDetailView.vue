<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@stores/ui'
import bouquetsService from '@services/bouquets'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()

const bouquet = ref(null)
const streams = ref([])
const loading = ref(true)
const streamId = ref('')

onMounted(async () => {
  try {
    const res = await bouquetsService.get(route.params.id)
    bouquet.value = res.data || res
    streams.value = bouquet.value.streams || []
  } catch (error) {
    uiStore.toastError('Failed to load bouquet')
    router.push({ name: 'bouquets' })
  } finally {
    loading.value = false
  }
})

async function addStream() {
  const id = Number(streamId.value)
  if (!id) return
  try {
    await bouquetsService.addStream(route.params.id, id)
    uiStore.toastSuccess('Stream added to bouquet')
    streamId.value = ''
    const res = await bouquetsService.get(route.params.id)
    bouquet.value = res.data || res
    streams.value = bouquet.value.streams || []
  } catch (error) {
    uiStore.toastError('Failed to add stream')
  }
}

async function removeStream(id) {
  try {
    await bouquetsService.removeStream(route.params.id, id)
    streams.value = streams.value.filter(s => s.id !== id)
    uiStore.toastSuccess('Stream removed')
  } catch (error) {
    uiStore.toastError('Failed to remove stream')
  }
}
</script>

<template>
  <div v-if="loading" class="card"><div class="card-body text-center py-12 text-dark-400">Loading...</div></div>

  <div v-else-if="bouquet" class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">{{ bouquet.name }}</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">{{ bouquet.description || 'No description' }}</p>
      </div>
      <router-link to="/bouquets" class="btn-secondary">Back</router-link>
    </div>

    <div class="card">
      <div class="card-body flex flex-col sm:flex-row gap-3">
        <input v-model="streamId" type="number" min="1" class="input max-w-xs" placeholder="Stream ID" />
        <button @click="addStream" class="btn-primary">Add Stream</button>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Streams ({{ streams.length }})</h3></div>
      <div class="table-container border-0 rounded-none">
        <table class="table">
          <thead><tr><th class="w-24">ID</th><th>Name</th><th class="w-28"></th></tr></thead>
          <tbody>
            <tr v-for="stream in streams" :key="stream.id">
              <td>{{ stream.id }}</td>
              <td class="font-medium text-dark-900 dark:text-white">{{ stream.name || stream.stream_display_name }}</td>
              <td><button @click="removeStream(stream.id)" class="btn-danger btn-sm">Remove</button></td>
            </tr>
            <tr v-if="streams.length === 0"><td colspan="3" class="text-center py-10 text-dark-400">No streams in this bouquet</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
