import api from './api'

export const categoriesService = {
  async list(params = {}) {
    const { data } = await api.get('/categories', { params })
    return data
  },
  async create(payload) {
    const { data } = await api.post('/categories', payload)
    return data
  },
  async update(id, payload) {
    const { data } = await api.put(`/categories/${id}`, payload)
    return data
  },
  async delete(id) {
    await api.delete(`/categories/${id}`)
  },
  async reorder(orderedIds) {
    const { data } = await api.patch('/categories/order', { ids: orderedIds })
    return data
  },
}

export default categoriesService
