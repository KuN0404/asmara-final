import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import authService from '@/services/authService'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || null)

  const isAuthenticated = computed(() => !!token.value)

  const hasRole = (role) => {
    if (!user.value?.roles) return false
    return user.value.roles.some((r) => r.name === role)
  }

  const setToken = (newToken) => {
    token.value = newToken
    localStorage.setItem('token', newToken)
  }

  const setUser = (userData) => {
    user.value = userData
  }

  const clearAuth = () => {
    token.value = null
    user.value = null
    localStorage.removeItem('token')
  }

  const login = async (credentials) => {
    try {
      const data = await authService.login(credentials)
      setToken(data.token)
      setUser(data.user)
      return data
    } catch (error) {
      throw error
    }
  }

  const logout = async () => {
    // Store current token before clearing
    const currentToken = token.value
    
    // Clear local state FIRST to prevent infinite loops
    clearAuth()
    
    // Only call API logout if we had a token
    if (currentToken) {
      try {
        await authService.logout()
      } catch (error) {
        // Ignore logout errors - we've already cleared local state
        console.warn('Logout API failed (token may have expired):', error.message)
      }
    }
  }

  const checkAuth = async () => {
    if (!token.value) return false

    try {
      const data = await authService.me()
      setUser(data.user)
      return true
    } catch (error) {
      // Token is invalid, clear auth state
      clearAuth()
      return false
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    hasRole,
    login,
    logout,
    checkAuth,
  }
})

