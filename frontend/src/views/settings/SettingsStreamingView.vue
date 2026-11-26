<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useUiStore } from '@stores/ui'
import settingsService from '@services/settings'

const uiStore = useUiStore()
const form = reactive({
  ffmpeg_version: '7.1',
  segment_time: 6,
  hls_window: 6,
  client_prebuffer: 30,
  max_connections: 1000,
  fanout_enabled: true,
})
const loading = ref(true)
const saving = ref(false)

onMounted(async () => {
  try {
    const res = await settingsService.section('streaming')
    Object.assign(form, res.data || res)
  } catch (error) {
    uiStore.toastError('Failed to load streaming settings')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  try {
    await settingsService.updateSection('streaming', form)
    uiStore.toastSuccess('Streaming settings saved')
  } catch (error) {
    uiStore.toastError('Failed to save streaming settings')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="save" class="card">
    <div class="card-header"><h3 class="font-semibold text-dark-900 dark:text-white">Streaming</h3></div>
    <div class="card-body space-y-5">
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label for="ffmpeg_version" class="label">FFmpeg build</label>
          <select id="ffmpeg_version" v-model="form.ffmpeg_version" class="input">
            <option value="8.1">8.1</option>
            <option value="7.1">7.1</option>
            <option value="4.4">4.4</option>
          </select>
        </div>
        <div>
          <label for="segment_time" class="label">Segment time (s)</label>
          <input id="segment_time" v-model.number="form.segment_time" type="number" min="1" max="30" class="input" />
        </div>
        <div>
          <label for="hls_window" class="label">HLS window</label>
          <input id="hls_window" v-model.number="form.hls_window" type="number" min="3" max="20" class="input" />
        </div>
        <div>
          <label for="client_prebuffer" class="label">Client prebuffer (s)</label>
          <input id="client_prebuffer" v-model.number="form.client_prebuffer" type="number" min="5" max="120" class="input" />
        </div>
        <div>
          <label for="max_connections" class="label">Max connections</label>
          <input id="max_connections" v-model.number="form.max_connections" type="number" min="1" class="input" />
        </div>
      </div>
      <label class="flex items-center gap-2 text-sm text-dark-700 dark:text-dark-300">
        <input v-model="form.fanout_enabled" type="checkbox" class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500" />
        Enable fanout delivery (<code class="text-xs">xc_fanout</code>)
      </label>
      <p class="text-xs text-dark-500 dark:text-dark-400">
        Ring seconds = max(HLS window × segment time, prebuffer + segment time), capped at 120 s.
      </p>
    </div>
    <div class="card-footer flex justify-end">
      <button type="submit" :disabled="saving || loading" class="btn-primary">{{ saving ? 'Saving…' : 'Save changes' }}</button>
    </div>
  </form>
</template>
