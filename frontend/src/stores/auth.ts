import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { api, getStatus } from '@/lib/api'
import type { AuthUser } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  /** Ya se consultó al servidor si hay una sesión abierta. */
  const checked = ref(false)

  const isLoggedIn = computed(() => user.value !== null)

  async function fetchUser() {
    try {
      const { data } = await api.get('/user')
      user.value = data.data
    } catch (error) {
      if (getStatus(error) !== 401) throw error
      user.value = null
    } finally {
      checked.value = true
    }
  }

  /** Los errores (422 credenciales, 429 bloqueo) se propagan al formulario. */
  async function login(email: string, password: string) {
    await api.get('/csrf')
    const { data } = await api.post('/login', { email, password })
    user.value = data.data
    checked.value = true
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      clear()
    }
  }

  /** Olvida la sesión en el navegador (cierre voluntario o sesión vencida). */
  function clear() {
    user.value = null
    checked.value = true
  }

  return { user, checked, isLoggedIn, fetchUser, login, logout, clear }
})
