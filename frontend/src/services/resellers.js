import api from './api'

export const resellersService = {
  async list(params = {}) {
    const { data } = await api.get('/resellers', { params })
    return data
  },
  async get(id) {
    const { data } = await api.get(`/resellers/${id}`)
    return data
  },
  async create(payload) {
    const { data } = await api.post('/resellers', payload)
    return data
  },
  async update(id, payload) {
    const { data } = await api.put(`/resellers/${id}`, payload)
    return data
  },
  async delete(id) {
    await api.delete(`/resellers/${id}`)
  },
}

export default resellersService
