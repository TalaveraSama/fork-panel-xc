import api from './api'

export const toolsService = {
  async diagnostics() {
    const { data } = await api.get('/tools/diagnostics')
    return data
  },
  async testStream(payload) {
    const { data } = await api.post('/tools/diagnostics/stream', payload)
    return data
  },
  async database() {
    const { data } = await api.get('/tools/database')
    return data
  },
  async runMigrations(payload = {}) {
    const { data } = await api.post('/tools/database/migrate', payload)
    return data
  },
  async cache() {
    const { data } = await api.get('/tools/cache')
    return data
  },
  async clearCache(payload = {}) {
    const { data } = await api.post('/tools/cache/clear', payload)
    return data
  },
}

export default toolsService
