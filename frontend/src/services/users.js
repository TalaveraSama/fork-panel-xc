import api from './api'

export const usersService = {
  // List users with filters
  async list(params = {}) {
    const { data } = await api.get('/users', { params })
    return data
  },

  // Get single user
  async get(id) {
    const { data } = await api.get(`/users/${id}`)
    return data
  },

  // Create user
  async create(userData) {
    const { data } = await api.post('/users', userData)
    return data
  },

  // Update user
  async update(id, userData) {
    const { data } = await api.put(`/users/${id}`, userData)
    return data
  },

  // Delete user
  async delete(id) {
    await api.delete(`/users/${id}`)
  },

  // Bulk delete
  async bulkDelete(ids) {
    await api.delete('/users', { data: { ids } })
  },

  // Bulk update
  async bulkUpdate(ids, updates) {
    const { data } = await api.patch('/users/bulk', { ids, updates })
    return data
  },

  // Get user stats
  async getStats(id) {
    const { data } = await api.get(`/users/${id}/stats`)
    return data
  },

  // Get user activity
  async getActivity(id, params = {}) {
    const { data } = await api.get(`/users/${id}/activity`, { params })
    return data
  },

  // Reset password
  async resetPassword(id, newPassword) {
    const { data } = await api.post(`/users/${id}/reset-password`, { password: newPassword })
    return data
  },

  // Toggle user status
  async toggleStatus(id, enabled) {
    const { data } = await api.patch(`/users/${id}/status`, { enabled })
    return data
  },

  // Assign bouquet
  async assignBouquet(id, bouquetId) {
    const { data } = await api.post(`/users/${id}/bouquets`, { bouquet_id: bouquetId })
    return data
  },

  // Remove bouquet
  async removeBouquet(id, bouquetId) {
    await api.delete(`/users/${id}/bouquets/${bouquetId}`)
  },
}

export default usersService