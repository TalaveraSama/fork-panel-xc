<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import toolsService from '@services/tools'

const uiStore = useUiStore()
const data = ref(null)
const loading = ref(false)
const testUrl = ref('')
const testResult = ref(null)
const testing = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await toolsService.diagnostics()
    data.value = res.data || res
  } catch (error) {
    uiStore.toastError('Failed to load diagnostics')
  } finally {
    loading.value = false
  }
}

async function testStream() {
  testing.value = true
  testResult.value = null
  try {
    const res = await toolsService.testStream({ url: testUrl.value })
    testResult.value = res.data || res
  } catch (error) {
    uiStore.toastError('Stream test failed')
  } finally {
    testing.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="card">
      <div class="card-header flex items-center justify-between">
        <h3 class="font-semibold text-dark-900 dark:text-white">System diagnostics</h3>
        <button @click="load" :disabled="loading" class="btn-secondary btn-sm">Refresh</button>
      </div>
      <div class="card-body">
        <div v-if="loading" class="text-dark-400 text-sm">Loading...</div>
        <dl v-else-if="data" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div v-for="(value, key) in data" :key="key" class="flex justify-between gap-4 border-b border-dark-100 dark:border-dark-800 pb-2">
            <dt class="text-dark-500 dark:text-dark-400 capitalize">{{ String(key).replaceAll('_', ' ') }}</dt>
            <dd class="font-medium text-dark-900 dark:text-white text-right break-all">{{ value }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Test a stream URL</h3></div>
      <div class="card-body space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
          <input v-model="testUrl" type="url" class="input flex-1" placeholder="http://example.com/stream.m3u8" />
          <button @click="testStream" :disabled="testing || !testUrl" class="btn-primary">{{ testing ? 'Testing…' : 'Test' }}</button>
        </div>
        <div v-if="testResult" class="rounded-lg border border-dark-200 dark:border-dark-700 p-4 text-sm space-y-1">
          <p><span class="text-dark-500 dark:text-dark-400">Success:</span> <span :class="testResult.success ? 'text-green-600' : 'text-red-600'">{{ testResult.success }}</span></p>
          <p><span class="text-dark-500 dark:text-dark-400">Latency:</span> {{ testResult.latency }} ms</p>
          <p v-if="testResult.error"><span class="text-dark-500 dark:text-dark-400">Error:</span> {{ testResult.error }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
