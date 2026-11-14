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

  /**
   * Primer paso. Devuelve 'two_factor' si la cuenta pide un código (todavía sin sesión).
   * Los errores (422 credenciales, 429 bloqueo) se propagan al formulario.
   */
  async function login(email: string, password: string): Promise<'ok' | 'two_factor'> {
    await api.get('/csrf')
    const response = await api.post('/login', { email, password })

    if (response.status === 202) return 'two_factor'

    user.value = response.data.data
    checked.value = true
    return 'ok'
  }

  /** Segundo paso: código de la app o código de recuperación. */
  async function verifyTwoFactor(payload: { code?: string; recovery_code?: string }) {
    const { data } = await api.post('/login/two-factor', payload)
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

  return { user, checked, isLoggedIn, fetchUser, login, verifyTwoFactor, logout, clear }
})
