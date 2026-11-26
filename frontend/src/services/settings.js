import api from './api'

export const settingsService = {
  async all() {
    const { data } = await api.get('/settings')
    return data
  },
  async section(name) {
    const { data } = await api.get(`/settings/${name}`)
    return data
  },
  async updateSection(name, values) {
    const { data } = await api.put(`/settings/${name}`, values)
    return data
  },
  async backup() {
    const { data } = await api.get('/settings/backup')
    return data
  },
  async createBackup(payload = {}) {
    const { data } = await api.post('/settings/backup', payload)
    return data
  },
  async logs(params = {}) {
    const { data } = await api.get('/settings/logs', { params })
    return data
  },
}

export default settingsService
