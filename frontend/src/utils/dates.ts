export const DAY_MS = 24 * 60 * 60 * 1000

/** Convierte 'YYYY-MM-DD' en una fecha local (evita el corrimiento por zona horaria). */
export function parseISODate(value: string): Date {
  const [year, month, day] = value.split('-').map(Number)
  return new Date(year!, month! - 1, day!)
}

export function toISODate(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export function startOfDay(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

export function addDays(date: Date, days: number): Date {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days)
}

/** Lunes de la semana de `date`. */
export function startOfWeek(date: Date): Date {
  const day = date.getDay() // 0 = domingo
  const diff = day === 0 ? -6 : 1 - day
  return addDays(startOfDay(date), diff)
}

export function daysBetween(from: Date, to: Date): number {
  return Math.round((startOfDay(to).getTime() - startOfDay(from).getTime()) / DAY_MS)
}

const shortFormatter = new Intl.DateTimeFormat('es-AR', {
  day: '2-digit',
  month: 'short',
})

/** '2026-11-02' → '02 nov' */
export function formatShortDate(value: string | Date): string {
  const date = typeof value === 'string' ? parseISODate(value) : value
  return shortFormatter.format(date).replace('.', '').replace('-', ' ')
}

const currencyFormatter = new Intl.NumberFormat('es-AR', {
  style: 'currency',
  currency: 'ARS',
  maximumFractionDigits: 0,
})

/** 1250000 → '$ 1,25 M'; 450000 → '$ 450 mil'. Para ejes de gráficos. */
export function formatCompactCurrency(value: number): string {
  if (value >= 1_000_000) {
    return `$ ${(value / 1_000_000).toLocaleString('es-AR', { maximumFractionDigits: 2 })} M`
  }
  if (value >= 1_000) return `$ ${Math.round(value / 1_000)} mil`
  return `$ ${value}`
}

export function formatCurrency(value: number): string {
  return currencyFormatter.format(value)
}
