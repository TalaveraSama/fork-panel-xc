<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Chart, registerables } from 'chart.js'
import { Line, Doughnut } from 'vue-chartjs'
import { useUiStore } from '@stores/ui'
import api from '@services/api'
import { formatNumber, formatDuration } from '@utils/format'

Chart.register(...registerables)

const uiStore = useUiStore()

const stats = ref({
  totalStreams: 0,
  onlineStreams: 0,
  offlineStreams: 0,
  totalUsers: 0,
  activeUsers: 0,
  totalConnections: 0,
  bandwidth: 0,
  cpuUsage: 0,
  memoryUsage: 0,
  diskUsage: 0,
})

const bandwidthChartData = ref({
  labels: [],
  datasets: [
    { label: 'Inbound (Mbps)', data: [], borderColor: '#ea580c', backgroundColor: 'rgba(234, 88, 12, 0.12)', tension: 0.4, fill: true },
    { label: 'Outbound (Mbps)', data: [], borderColor: '#22c55e', backgroundColor: 'rgba(34, 197, 94, 0.12)', tension: 0.4, fill: true },
  ],
})

const connectionsChartData = ref({
  labels: [],
  datasets: [
    { label: 'Active Connections', data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.12)', tension: 0.4, fill: true },
  ],
})

const streamsByTypeData = ref({
  labels: ['Live', 'VOD', 'Series', 'Radio'],
  datasets: [
    {
      data: [0, 0, 0, 0],
      backgroundColor: ['rgba(234, 88, 12, 0.85)', 'rgba(34, 197, 94, 0.85)', 'rgba(59, 130, 246, 0.85)', 'rgba(168, 85, 247, 0.85)'],
      borderWidth: 0,
    },
  ],
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top', labels: { color: '#64748b', usePointStyle: true, padding: 16, font: { size: 11 } } },
    tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 },
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } },
    y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.2)' }, ticks: { color: '#94a3b8', font: { size: 10 } } },
  },
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '60%',
  plugins: {
    legend: { position: 'bottom', labels: { color: '#64748b', usePointStyle: true, padding: 16, font: { size: 11 } } },
    tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 },
  },
}

const recentActivity = ref([])
const loading = ref(false)

let statsInterval = null
let ws = null
let reconnectTimer = null

async function fetchStats() {
  try {
    const { data } = await api.get('/dashboard/stats')

    stats.value = {
      totalStreams: data.totalStreams ?? 0,
      onlineStreams: data.onlineStreams ?? 0,
      offlineStreams: (data.totalStreams ?? 0) - (data.onlineStreams ?? 0),
      totalUsers: data.totalUsers ?? 0,
      activeUsers: data.activeUsers ?? 0,
      totalConnections: data.totalConnections ?? 0,
      bandwidth: data.bandwidth ?? 0,
      cpuUsage: data.cpu ?? 0,
      memoryUsage: data.memory ?? 0,
      diskUsage: data.disk ?? 0,
    }

    if (data.history) {
      bandwidthChartData.value.labels = data.history.labels ?? []
      bandwidthChartData.value.datasets[0].data = data.history.inbound ?? []
      bandwidthChartData.value.datasets[1].data = data.history.outbound ?? []

      connectionsChartData.value.labels = data.history.labels ?? []
      connectionsChartData.value.datasets[0].data = data.history.connections ?? []
    }

    if (data.streamsByType) {
      streamsByTypeData.value.datasets[0].data = [
        data.streamsByType.live ?? 0,
        data.streamsByType.vod ?? 0,
        data.streamsByType.series ?? 0,
        data.streamsByType.radio ?? 0,
      ]
    }
  } catch (error) {
    console.error('Failed to fetch dashboard stats:', error)
  }
}

async function fetchRecentActivity() {
  try {
    const { data } = await api.get('/dashboard/activity', { params: { limit: 10 } })
    recentActivity.value = data.data ?? []
  } catch (error) {
    console.error('Failed to fetch recent activity:', error)
  }
}

function connectWebSocket() {
  const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
  const url = `${protocol}//${window.location.host}/ws/dashboard`

  try {
    ws = new WebSocket(url)
  } catch (error) {
    console.warn('WebSocket unavailable:', error)
    return
  }

  ws.onmessage = (event) => {
    try {
      const message = JSON.parse(event.data)
      if (message.type === 'stats_update') {
        stats.value = { ...stats.value, ...message.data }
      } else if (message.type === 'activity') {
        recentActivity.value.unshift(message.data)
        recentActivity.value = recentActivity.value.slice(0, 10)
      }
    } catch (error) {
      console.error('WS message parse error:', error)
    }
  }

  ws.onclose = () => {
    reconnectTimer = setTimeout(connectWebSocket, 5000)
  }
}

async function refreshAll() {
  loading.value = true
  await Promise.all([fetchStats(), fetchRecentActivity()])
  loading.value = false
}

onMounted(() => {
  refreshAll()
  connectWebSocket()
  statsInterval = setInterval(fetchStats, 30000)
})

onUnmounted(() => {
  if (statsInterval) clearInterval(statsInterval)
  if (reconnectTimer) clearTimeout(reconnectTimer)
  if (ws) ws.close()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Dashboard</h1>
        <p class="text-dark-500 dark:text-dark-400 mt-1">Real-time overview of your IPTV panel</p>
      </div>
      <button @click="refreshAll" :disabled="loading" class="btn-secondary">
        <svg class="w-4 h-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Refresh
      </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="card">
        <div class="card-body">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-dark-500 dark:text-dark-400">Total Streams</p>
              <p class="text-3xl font-bold text-dark-900 dark:text-white mt-1">{{ formatNumber(stats.totalStreams) }}</p>
            </div>
            <div class="w-14 h-14 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
              <svg class="w-7 h-7 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
          <div class="mt-4 flex gap-5 text-sm">
            <span class="flex items-center gap-2 text-green-600 dark:text-green-400"><span class="w-2 h-2 rounded-full bg-current" />{{ formatNumber(stats.onlineStreams) }} Online</span>
            <span class="flex items-center gap-2 text-red-600 dark:text-red-400"><span class="w-2 h-2 rounded-full bg-current" />{{ formatNumber(stats.offlineStreams) }} Offline</span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-dark-500 dark:text-dark-400">Total Users</p>
              <p class="text-3xl font-bold text-dark-900 dark:text-white mt-1">{{ formatNumber(stats.totalUsers) }}</p>
            </div>
            <div class="w-14 h-14 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
              <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
          </div>
          <div class="mt-4 flex gap-5 text-sm">
            <span class="flex items-center gap-2 text-green-600 dark:text-green-400"><span class="w-2 h-2 rounded-full bg-current" />{{ formatNumber(stats.activeUsers) }} Active</span>
            <span class="flex items-center gap-2 text-blue-600 dark:text-blue-400"><span class="w-2 h-2 rounded-full bg-current" />{{ formatNumber(stats.totalConnections) }} Connections</span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-dark-500 dark:text-dark-400">Bandwidth</p>
              <p class="text-3xl font-bold text-dark-900 dark:text-white mt-1">{{ formatNumber(stats.bandwidth, 1) }} <span class="text-base font-medium">Mbps</span></p>
            </div>
            <div class="w-14 h-14 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <svg class="w-7 h-7 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
              </svg>
            </div>
          </div>
          <div class="mt-4 h-2 bg-dark-100 dark:bg-dark-800 rounded-full overflow-hidden">
            <div class="h-full bg-primary-600 rounded-full transition-all duration-500" :style="{ width: Math.min((stats.bandwidth / 1000) * 100, 100) + '%' }" />
          </div>
          <p class="text-xs text-dark-500 dark:text-dark-400 mt-1.5">of 1 Gbps capacity</p>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-dark-500 dark:text-dark-400">System Health</p>
              <p class="text-3xl font-bold mt-1" :class="stats.cpuUsage > 80 || stats.memoryUsage > 85 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                {{ stats.cpuUsage > 80 || stats.memoryUsage > 85 || stats.diskUsage > 90 ? 'Warning' : 'Healthy' }}
              </p>
            </div>
          </div>
          <div class="mt-4 space-y-2.5">
            <div v-for="metric in [
              { label: 'CPU', value: stats.cpuUsage, color: 'bg-primary-600' },
              { label: 'Memory', value: stats.memoryUsage, color: 'bg-green-600' },
              { label: 'Disk', value: stats.diskUsage, color: 'bg-blue-600' },
            ]" :key="metric.label">
              <div class="flex justify-between text-xs mb-1">
                <span class="text-dark-600 dark:text-dark-400">{{ metric.label }}</span>
                <span class="font-medium text-dark-900 dark:text-white">{{ metric.value }}%</span>
              </div>
              <div class="h-1.5 bg-dark-100 dark:bg-dark-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500" :class="metric.color" :style="{ width: metric.value + '%' }" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <div class="card xl:col-span-2">
        <div class="card-header flex items-center justify-between">
          <h3 class="text-lg font-semibold text-dark-900 dark:text-white">Bandwidth Usage (24h)</h3>
        </div>
        <div class="card-body">
          <div class="h-72">
            <Line v-if="bandwidthChartData.labels.length" :data="bandwidthChartData" :options="chartOptions" />
            <div v-else class="h-full flex items-center justify-center text-dark-400 text-sm">No bandwidth data yet</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="text-lg font-semibold text-dark-900 dark:text-white">Streams by Type</h3>
        </div>
        <div class="card-body">
          <div class="h-72">
            <Doughnut :data="streamsByTypeData" :options="doughnutOptions" />
          </div>
        </div>
      </div>

      <div class="card xl:col-span-3">
        <div class="card-header flex items-center justify-between">
          <h3 class="text-lg font-semibold text-dark-900 dark:text-white">Active Connections (24h)</h3>
        </div>
        <div class="card-body">
          <div class="h-64">
            <Line v-if="connectionsChartData.labels.length" :data="connectionsChartData" :options="chartOptions" />
            <div v-else class="h-full flex items-center justify-center text-dark-400 text-sm">No connection data yet</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold text-dark-900 dark:text-white">Recent Activity</h3>
        <router-link to="/tools/diagnostics" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">View diagnostics</router-link>
      </div>
      <div class="table-container border-0 rounded-none">
        <table class="table">
          <thead>
            <tr>
              <th class="w-32">Time</th>
              <th>Event</th>
              <th class="w-48">User</th>
              <th class="w-40">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="activity in recentActivity" :key="activity.id">
              <td class="text-dark-500 dark:text-dark-400">{{ formatDuration(activity.timestamp) }}</td>
              <td class="font-medium text-dark-900 dark:text-white">{{ activity.event }}</td>
              <td>{{ activity.user || 'System' }}</td>
              <td>
                <span :class="['badge', activity.status === 'success' ? 'badge-success' : activity.status === 'warning' ? 'badge-warning' : activity.status === 'error' ? 'badge-danger' : 'badge-info']">
                  {{ activity.status }}
                </span>
              </td>
            </tr>
            <tr v-if="recentActivity.length === 0">
              <td colspan="4" class="text-center py-10 text-dark-400 dark:text-dark-500">No recent activity</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <router-link to="/streams/create" class="card p-5 hover:border-primary-400 dark:hover:border-primary-600 transition-colors">
        <h4 class="font-semibold text-dark-900 dark:text-white">Add Stream</h4>
        <p class="text-sm text-dark-500 dark:text-dark-400 mt-1">Create a new live stream</p>
      </router-link>
      <router-link to="/movies/create" class="card p-5 hover:border-primary-400 dark:hover:border-primary-600 transition-colors">
        <h4 class="font-semibold text-dark-900 dark:text-white">Add Movie</h4>
        <p class="text-sm text-dark-500 dark:text-dark-400 mt-1">Add a new VOD movie</p>
      </router-link>
      <router-link to="/users/create" class="card p-5 hover:border-primary-400 dark:hover:border-primary-600 transition-colors">
        <h4 class="font-semibold text-dark-900 dark:text-white">Create User</h4>
        <p class="text-sm text-dark-500 dark:text-dark-400 mt-1">Add a new user account</p>
      </router-link>
      <router-link to="/resellers/create" class="card p-5 hover:border-primary-400 dark:hover:border-primary-600 transition-colors">
        <h4 class="font-semibold text-dark-900 dark:text-white">Add Reseller</h4>
        <p class="text-sm text-dark-500 dark:text-dark-400 mt-1">Create a new reseller</p>
      </router-link>
    </div>
  </div>
</template>
