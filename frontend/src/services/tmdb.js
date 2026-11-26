import api from './api'

export const tmdbService = {
  async search(query, type = 'movie', year = null) {
    const { data } = await api.get('/tmdb/search', {
      params: { query, type, ...(year ? { year } : {}) },
    })
    return data
  },

  async details(type, id) {
    const { data } = await api.get(`/tmdb/details/${type}/${id}`)
    return data
  },

  async settings() {
    const { data } = await api.get('/tmdb/settings')
    return data.data || data
  },

  async saveSettings(payload) {
    const { data } = await api.put('/tmdb/settings', payload)
    return data.data || data
  },
}

export default tmdbService
