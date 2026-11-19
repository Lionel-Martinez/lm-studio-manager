/**
 * En qué se está ejecutando la app:
 * - navegador (web): la sesión es una cookie y la API está en el mismo origen o en VITE_API_URL;
 * - app nativa (Tauri en Linux, Capacitor en Android / iOS): sin cookies; la dirección del
 *   servidor la elige quien la usa y cada dispositivo tiene su propio token.
 *
 * `VITE_TARGET=native` lo fija `npm run build:native` (ver .env.native).
 */
export const isNative = import.meta.env.VITE_TARGET === 'native'

interface CapacitorGlobal {
  getPlatform?: () => string
}

const capacitor = (): CapacitorGlobal | undefined => (window as unknown as { Capacitor?: CapacitorGlobal }).Capacitor

export type DevicePlatform = 'linux' | 'android' | 'ios' | 'other'

export function devicePlatform(): DevicePlatform {
  if (!isNative) return 'other'
  const cap = capacitor()?.getPlatform?.()
  if (cap === 'android' || cap === 'ios') return cap
  if ('__TAURI_INTERNALS__' in window) return 'linux'
  return 'other'
}

/** Celular o tableta con la app instalada (Capacitor). */
export const isMobileApp = isNative && ['android', 'ios'].includes(devicePlatform())

/** Nombre con el que este dispositivo aparece en Cuenta → Dispositivos. */
export function defaultDeviceName(): string {
  switch (devicePlatform()) {
    case 'android':
      return 'Celular Android'
    case 'ios':
      return 'iPhone / iPad'
    case 'linux':
      return 'PC con Linux'
    default:
      return 'Dispositivo'
  }
}
