import api from './api'

export default {
  async login(credentials) {
    try {
      const response = await api.post('/login', credentials)
      return response.data
    } catch (error) {
      throw error.response?.data || error
    }
  },

  async logout() {
    const response = await api.post('/logout')
    return response.data
  },

  async me() {
    const response = await api.get('/me')
    return response.data
  },
}
