// Genera dist/_headers para Cloudflare Pages: cabeceras de seguridad y CSP con la dirección real de la API.
import { writeFileSync } from 'node:fs'

const api = (process.env.VITE_API_URL ?? '').trim().replace(/\/+$/, '')

if (!api && process.env.CF_PAGES) {
  console.error('Falta la variable VITE_API_URL en Cloudflare Pages (ej. https://api.tudominio.com.ar).')
  process.exit(1)
}

if (api && !/^https:\/\/[a-z0-9.-]+$/i.test(api)) {
  console.error(`VITE_API_URL no es válida: "${api}". Debe ser https://dominio, sin ruta ni barra final.`)
  process.exit(1)
}

const csp = [
  "default-src 'self'",
  "script-src 'self'",
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data:",
  "font-src 'self'",
  `connect-src 'self'${api ? ` ${api}` : ''}`,
  "frame-ancestors 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "object-src 'none'",
].join('; ')

const headers = `/*
  Content-Security-Policy: ${csp}
  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
  Strict-Transport-Security: max-age=31536000; includeSubDomains
  Cross-Origin-Opener-Policy: same-origin

/assets/*
  Cache-Control: public, max-age=31536000, immutable
`

writeFileSync(new URL('../dist/_headers', import.meta.url), headers)
console.log(`dist/_headers generado (API: ${api || 'mismo origen'}).`)
