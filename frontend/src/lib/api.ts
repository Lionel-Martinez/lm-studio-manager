import axios, { isAxiosError } from 'axios'

/**
 * Cliente HTTP único de la aplicación.
 * En desarrollo, Vite redirige /api al backend de Laravel (ver vite.config.ts).
 *
 * La sesión viaja en una cookie HttpOnly (el navegador la envía sola) y axios
 * copia la cookie XSRF-TOKEN al encabezado X-XSRF-TOKEN: protección CSRF de Laravel.
 */
export const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
  withCredentials: true,
  withXSRFToken: true,
})

export type FieldErrors = Record<string, string[]>

/** Devuelve los errores de validación (HTTP 422) de Laravel, o `null` si es otro tipo de error. */
export function getValidationErrors(error: unknown): FieldErrors | null {
  if (isAxiosError(error) && error.response?.status === 422) {
    return (error.response.data?.errors as FieldErrors | undefined) ?? {}
  }
  return null
}

/** Código HTTP de un error de axios, si lo hay. */
export function getStatus(error: unknown): number | undefined {
  return isAxiosError(error) ? error.response?.status : undefined
}

/**
 * Si el servidor dice que la sesión venció (401) o el token CSRF caducó (419),
 * se avisa a la app para que vuelva al login. Se registra desde main.ts.
 */
export function onSessionExpired(handler: () => void) {
  api.interceptors.response.use(
    (response) => response,
    (error) => {
      const status = getStatus(error)
      const url = String(error.config?.url ?? '')
      const isAuthProbe = url.startsWith('/login') || url.startsWith('/user') || url.startsWith('/csrf')

      if ((status === 401 || status === 419) && !isAuthProbe) handler()
      return Promise.reject(error)
    },
  )
}
