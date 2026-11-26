import api from './api'

export const libraryService = {
  async settings() {
    const { data } = await api.get('/library/settings')
    return data.data || data
  },
  async saveSettings(payload) {
    const { data } = await api.put('/library/settings', payload)
    return data.data || data
  },
  async status() {
    const { data } = await api.get('/library/status')
    return data.data || data
  },
  async scan(payload = {}) {
    const { data } = await api.post('/library/scan', payload)
    return data
  },
}

export default libraryService
