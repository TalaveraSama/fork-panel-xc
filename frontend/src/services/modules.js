import api from './api'

export const modulesService = {
  async list() {
    const { data } = await api.get('/modules')
    return data
  },
  async get(id) {
    const { data } = await api.get(`/modules/${id}`)
    return data
  },
  async install(id) {
    const { data } = await api.post(`/modules/${id}/install`)
    return data
  },
  async update(id) {
    const { data } = await api.post(`/modules/${id}/update`)
    return data
  },
  async uninstall(id) {
    const { data } = await api.post(`/modules/${id}/uninstall`)
    return data
  },
  async enable(id) {
    const { data } = await api.post(`/modules/${id}/enable`)
    return data
  },
  async disable(id) {
    const { data } = await api.post(`/modules/${id}/disable`)
    return data
  },
}

export default modulesService
