import api from './api'

export const streamsService = {
  // List streams with filters
  async list(params = {}) {
    const { data } = await api.get('/streams', { params })
    return data
  },

  // Get single stream
  async get(id) {
    const { data } = await api.get(`/streams/${id}`)
    return data
  },

  // Create stream
  async create(streamData) {
    const { data } = await api.post('/streams', streamData)
    return data
  },

  // Update stream
  async update(id, streamData) {
    const { data } = await api.put(`/streams/${id}`, streamData)
    return data
  },

  // Delete stream
  async delete(id) {
    await api.delete(`/streams/${id}`)
  },

  // Bulk delete
  async bulkDelete(ids) {
    await api.delete('/streams', { data: { ids } })
  },

  // Bulk update
  async bulkUpdate(ids, updates) {
    const { data } = await api.patch('/streams/bulk', { ids, updates })
    return data
  },

  // Get stream categories
  async getCategories() {
    const { data } = await api.get('/streams/categories')
    return data
  },

  // Get stream stats
  async getStats(id) {
    const { data } = await api.get(`/streams/${id}/stats`)
    return data
  },

  // Get stream audit log
  async getLogs(id, params = {}) {
    const { data } = await api.get(`/streams/${id}/logs`, { params })
    return data
  },

  // Active viewer sessions
  async getSessions(id) {
    const { data } = await api.get(`/streams/${id}/sessions`)
    return data
  },

  // Admin test-player URL
  async preview(id) {
    const { data } = await api.post(`/streams/${id}/preview`)
    return data.data || data
  },

  // Run a source monitor check now
  async monitor(id) {
    const { data } = await api.post(`/streams/${id}/monitor`)
    return data
  },

  // Check all sources now
  async monitorAll() {
    const { data } = await api.post('/streams/monitor-all')
    return data
  },

  // Restart stream (drop sessions + check source)
  async restart(id) {
    const { data } = await api.post(`/streams/${id}/restart`)
    return data
  },

  // Kick one viewer session
  async kickSession(id, sessionId) {
    const { data } = await api.delete(`/streams/${id}/sessions/${sessionId}`)
    return data
  },

  // Test stream
  async test(id) {
    const { data } = await api.post(`/streams/${id}/test`)
    return data
  },

  // Import M3U
  async importM3u(file, options = {}) {
    const formData = new FormData()
    formData.append('m3u_file', file)
    Object.entries(options).forEach(([key, value]) => {
      formData.append(key, value)
    })
    const { data } = await api.post('/streams/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  // Export streams
  async export(format = 'm3u', filters = {}) {
    const { data } = await api.get('/streams/export', {
      params: { format, ...filters },
      responseType: 'blob',
    })
    return data
  },
}

export default streamsService