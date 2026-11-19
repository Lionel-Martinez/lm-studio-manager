/**
 * Datos de la app nativa que se guardan en el dispositivo: dirección del servidor y token.
 * El token queda en el almacenamiento privado de la app (no se comparte con otras apps ni sitios).
 */
const SERVER_KEY = 'lm.server'
const TOKEN_KEY = 'lm.token'

function read(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

function write(key: string, value: string | null) {
  try {
    if (value === null) localStorage.removeItem(key)
    else localStorage.setItem(key, value)
  } catch {
    // Sin almacenamiento disponible: la sesión dura solo mientras la app esté abierta.
  }
}

export const getServer = () => read(SERVER_KEY)
export const setServer = (url: string | null) => write(SERVER_KEY, url)
export const getToken = () => read(TOKEN_KEY)
export const setToken = (token: string | null) => write(TOKEN_KEY, token)

/**
 * Normaliza lo que escribe el usuario: agrega https:// si falta y deja solo el origen.
 * Devuelve `null` si no es una dirección válida. Solo se aceptan conexiones https
 * (o http://localhost para pruebas): el token no debe viajar sin cifrar.
 */
export function normalizeServerUrl(input: string): string | null {
  let text = input.trim()
  if (!text) return null
  if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(text)) text = `https://${text}`

  try {
    const url = new URL(text)
    const local = url.hostname === 'localhost' || url.hostname === '127.0.0.1'
    if (url.protocol !== 'https:' && !(url.protocol === 'http:' && local)) return null
    return url.origin
  } catch {
    return null
  }
}
