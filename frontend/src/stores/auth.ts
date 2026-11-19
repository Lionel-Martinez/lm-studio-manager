import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { api, getStatus, NO_SERVER } from '@/lib/api'
import { getToken, setToken } from '@/lib/nativeSession'
import { defaultDeviceName, devicePlatform, isNative } from '@/lib/platform'
import type { AuthUser } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  /** Ya se consultó al servidor si hay una sesión abierta. */
  const checked = ref(false)

  const isLoggedIn = computed(() => user.value !== null)

  /** Segundo paso pendiente en la app nativa (en el navegador lo guarda la sesión del servidor). */
  const challenge = ref<string | null>(null)

  const device = () => ({ device_name: defaultDeviceName(), platform: devicePlatform() })

  async function fetchUser() {
    // App nativa sin token: no hay nada que preguntarle al servidor.
    if (isNative && !getToken()) {
      user.value = null
      checked.value = true
      return
    }

    try {
      const { data } = await api.get('/user')
      user.value = data.data
    } catch (error) {
      if (isNative && (error as Error).message === NO_SERVER) {
        user.value = null
      } else if (getStatus(error) === 401) {
        user.value = null
        if (isNative) setToken(null)
      } else {
        throw error
      }
    } finally {
      checked.value = true
    }
  }

  /**
   * Primer paso. Devuelve 'two_factor' si la cuenta pide un código (todavía sin sesión).
   * Los errores (422 credenciales, 429 bloqueo) se propagan al formulario.
   */
  async function login(email: string, password: string): Promise<'ok' | 'two_factor'> {
    if (!isNative) await api.get('/csrf')
    const response = await api.post('/login', isNative ? { email, password, ...device() } : { email, password })

    if (response.status === 202) {
      challenge.value = response.data.challenge ?? null
      return 'two_factor'
    }

    if (isNative) setToken(response.data.token)
    user.value = response.data.data
    checked.value = true
    return 'ok'
  }

  /** Segundo paso: código de la app o código de recuperación. */
  async function verifyTwoFactor(payload: { code?: string; recovery_code?: string }) {
    const { data } = await api.post('/login/two-factor', isNative ? { ...payload, challenge: challenge.value, ...device() } : payload)
    if (isNative) setToken(data.token)
    challenge.value = null
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
    if (isNative) setToken(null)
    user.value = null
    checked.value = true
  }

  return { user, checked, challenge, isLoggedIn, fetchUser, login, verifyTwoFactor, logout, clear }
})
