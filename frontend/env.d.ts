/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Dirección de la API en producción (sin barra final). Vacío en desarrollo. */
  readonly VITE_API_URL?: string
  /** 'native' al compilar las apps de escritorio y celular. */
  readonly VITE_TARGET?: string
}
