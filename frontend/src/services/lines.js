import api from './api'

export const linesService = {
  async list(params = {}) {
    const { data } = await api.get('/lines', { params })
    return data
  },
  async get(id) {
    const { data } = await api.get(`/lines/${id}`)
    return data.data || data
  },
  async create(payload) {
    const { data } = await api.post('/lines', payload)
    return data
  },
  async update(id, payload) {
    const { data } = await api.put(`/lines/${id}`, payload)
    return data
  },
  async delete(id) {
    await api.delete(`/lines/${id}`)
  },
  async resetPassword(id, password = '') {
    const { data } = await api.post(`/lines/${id}/reset-password`, password ? { password } : {})
    return data
  },
}

export default linesService
