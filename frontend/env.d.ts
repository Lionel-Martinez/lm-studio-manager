/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Dirección de la API en producción (sin barra final). Vacío en desarrollo. */
  readonly VITE_API_URL?: string
}
