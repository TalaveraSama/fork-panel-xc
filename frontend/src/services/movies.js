import api from './api'

export const moviesService = {
  async list(params = {}) {
    const { data } = await api.get('/movies', { params })
    return data
  },

  async get(id) {
    const { data } = await api.get(`/movies/${id}`)
    return data
  },

  async create(movieData) {
    const { data } = await api.post('/movies', movieData)
    return data
  },

  async update(id, movieData) {
    const { data } = await api.put(`/movies/${id}`, movieData)
    return data
  },

  async delete(id) {
    await api.delete(`/movies/${id}`)
  },

  async bulkDelete(ids) {
    await api.delete('/movies', { data: { ids } })
  },

  async getStats(id) {
    const { data } = await api.get(`/movies/${id}/stats`)
    return data
  },

  async importM3u(file, options = {}) {
    const formData = new FormData()
    formData.append('m3u_file', file)
    Object.entries(options).forEach(([key, value]) => {
      formData.append(key, value)
    })
    const { data } = await api.post('/movies/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },
}

export default moviesService