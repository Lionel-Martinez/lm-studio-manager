import axios, { isAxiosError } from 'axios'

/**
 * Cliente HTTP único de la aplicación.
 * En desarrollo, Vite redirige /api al backend de Laravel (ver vite.config.ts).
 */
export const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})

export type FieldErrors = Record<string, string[]>

/** Devuelve los errores de validación (HTTP 422) de Laravel, o `null` si es otro tipo de error. */
export function getValidationErrors(error: unknown): FieldErrors | null {
  if (isAxiosError(error) && error.response?.status === 422) {
    return (error.response.data?.errors as FieldErrors | undefined) ?? {}
  }
  return null
}
