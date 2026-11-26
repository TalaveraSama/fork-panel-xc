import api from './api'

export const epgService = {
  async overview(params = {}) {
    const { data } = await api.get('/epg', { params })
    return data
  },
  async sources() {
    const { data } = await api.get('/epg/sources')
    return data
  },
  async createSource(payload) {
    const { data } = await api.post('/epg/sources', payload)
    return data
  },
  async updateSource(id, payload) {
    const { data } = await api.put(`/epg/sources/${id}`, payload)
    return data
  },
  async deleteSource(id) {
    await api.delete(`/epg/sources/${id}`)
  },
  async syncSource(id) {
    const { data } = await api.post(`/epg/sources/${id}/sync`)
    return data
  },
  async channels(params = {}) {
    const { data } = await api.get('/epg/channels', { params })
    return data
  },
}

export default epgService
