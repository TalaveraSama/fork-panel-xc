import api from './api'

export const bouquetsService = {
  async list(params = {}) {
    const { data } = await api.get('/bouquets', { params })
    return data
  },
  async get(id) {
    const { data } = await api.get(`/bouquets/${id}`)
    return data
  },
  async create(payload) {
    const { data } = await api.post('/bouquets', payload)
    return data
  },
  async update(id, payload) {
    const { data } = await api.put(`/bouquets/${id}`, payload)
    return data
  },
  async delete(id) {
    await api.delete(`/bouquets/${id}`)
  },
  async addStream(id, streamId) {
    const { data } = await api.post(`/bouquets/${id}/streams`, { stream_id: streamId })
    return data
  },
  async removeStream(id, streamId) {
    await api.delete(`/bouquets/${id}/streams/${streamId}`)
  },
}

export default bouquetsService
