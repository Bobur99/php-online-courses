import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi, type User } from '../shared/api/authApi'
import { HttpError } from '../shared/api/http'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const storedToken = localStorage.getItem('auth_token')
  const token = ref(storedToken && storedToken !== 'undefined' && storedToken !== 'null' ? storedToken : null)
  if (!token.value) localStorage.removeItem('auth_token')
  const isAuthenticated = computed(() => Boolean(token.value))

  async function login(email: string, password: string) {
    const response = await authApi.login({ email, password })
    if (!response.token) throw new Error('Сервер не вернул токен авторизации.')
    token.value = response.token
    user.value = response.user
    localStorage.setItem('auth_token', response.token)
  }

  async function register(name: string, email: string, password: string, passwordConfirmation: string) {
    const response = await authApi.register({ name, email, password, password_confirmation: passwordConfirmation })
    if (!response.token) throw new Error('Сервер не вернул токен авторизации.')
    token.value = response.token
    user.value = response.user
    localStorage.setItem('auth_token', response.token)
  }

  async function updateProfile(name: string, email: string) {
    user.value = await authApi.updateProfile({ name, email })
  }

  async function logout() {
    if (token.value) await authApi.logout().catch(() => undefined)
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
  }

  async function loadProfile() {
    if (!token.value) return
    try {
      user.value = await authApi.profile()
    } catch (exception) {
      if (exception instanceof HttpError && [401, 403].includes(exception.status)) {
        token.value = null
        user.value = null
        localStorage.removeItem('auth_token')
      }
      throw exception
    }
  }

  return { user, token, isAuthenticated, login, register, updateProfile, logout, loadProfile }
})
