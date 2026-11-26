import api from './api'

export const seriesService = {
  async list(params = {}) {
    const { data } = await api.get('/series', { params })
    return data
  },

  async get(id) {
    const { data } = await api.get(`/series/${id}`)
    return data
  },

  async create(seriesData) {
    const { data } = await api.post('/series', seriesData)
    return data
  },

  async update(id, seriesData) {
    const { data } = await api.put(`/series/${id}`, seriesData)
    return data
  },

  async delete(id) {
    await api.delete(`/series/${id}`)
  },

  async getEpisodes(seriesId, params = {}) {
    const { data } = await api.get(`/series/${seriesId}/episodes`, { params })
    return data
  },

  async createEpisode(seriesId, episodeData) {
    const { data } = await api.post(`/series/${seriesId}/episodes`, episodeData)
    return data
  },

  async updateEpisode(seriesId, episodeId, episodeData) {
    const { data } = await api.put(`/series/${seriesId}/episodes/${episodeId}`, episodeData)
    return data
  },

  async deleteEpisode(seriesId, episodeId) {
    await api.delete(`/series/${seriesId}/episodes/${episodeId}`)
  },
}

export default seriesService