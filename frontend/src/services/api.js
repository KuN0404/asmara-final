import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Flag to prevent infinite logout loop
let isLoggingOut = false

api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    return config
  },
  (error) => Promise.reject(error),
)

api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Skip handling for logout endpoint or if already logging out
    const isLogoutRequest = error.config?.url?.includes('/logout')
    
    if (error.response?.status === 401 && !isLoggingOut && !isLogoutRequest) {
      isLoggingOut = true
      const authStore = useAuthStore()
      authStore.logout().finally(() => {
        isLoggingOut = false
      })
      router.push('/login')
    }
    return Promise.reject(error)
  },
)

export default api

