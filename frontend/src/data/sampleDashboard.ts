import type {
  ClientDistributionPoint,
  DeliveryItem,
  PendingPayment,
  RevenuePoint,
} from '@/types/dashboard'
import { addDays, startOfDay, toISODate } from '@/utils/dates'

/**
 * DATOS DE EJEMPLO.
 * Sirven para ver el diseño mientras el backend no está conectado (Etapa 2).
 * Las fechas son relativas a hoy para que el calendario siempre se vea coherente.
 * Cuando exista la API, `useDashboard` deja de usar este archivo.
 */
const today = startOfDay(new Date())
const day = (offset: number) => toISODate(addDays(today, offset))

export const sampleProjects: DeliveryItem[] = [
  {
    id: 1,
    client: 'Gimnasio Vital',
    service: 'App móvil',
    status: 'ready',
    entryDate: day(-30),
    startDate: day(-22),
    dueDate: day(3),
  },
  {
    id: 2,
    client: 'Estudio Contable Rojas',
    service: 'Página web estática',
    status: 'in_progress',
    entryDate: day(-14),
    startDate: day(-9),
    dueDate: day(9),
  },
  {
    id: 3,
    client: 'Ferretería Norte',
    service: 'App web',
    status: 'in_progress',
    entryDate: day(-21),
    startDate: day(-1),
    dueDate: day(38),
    delayReason: 'Falta el catálogo del cliente',
  },
  {
    id: 4,
    client: 'Cafetería Don Pepe',
    service: 'Menú digital',
    status: 'waiting',
    entryDate: day(-8),
    startDate: day(5),
    dueDate: day(19),
  },
  {
    id: 5,
    client: 'Panadería La Espiga',
    service: 'Menú digital',
    status: 'prospect',
    entryDate: day(-2),
    startDate: null,
    dueDate: null,
  },
]

export const samplePayments: PendingPayment[] = [
  { id: 1, client: 'Gimnasio Vital', concept: 'Saldo final', dueDate: day(-3), amount: 450000 },
  { id: 2, client: 'Estudio Contable Rojas', concept: 'Cuota 2 de 3', dueDate: day(1), amount: 120000 },
  { id: 3, client: 'Ferretería Norte', concept: 'Anticipo', dueDate: day(4), amount: 300000 },
]

const monthFormatter = new Intl.DateTimeFormat('es-AR', { month: 'short' })
const monthlyRevenue = [380000, 560000, 470000, 720000, 640000, 1250000]

/** Últimos seis meses, el último es el mes actual. */
export const sampleRevenue: RevenuePoint[] = monthlyRevenue.map((value, index) => {
  const date = new Date(today.getFullYear(), today.getMonth() - (monthlyRevenue.length - 1 - index), 1)
  return { label: monthFormatter.format(date).replace('.', ''), value }
})

export const sampleDistribution: ClientDistributionPoint[] = [
  { label: 'Orán', value: 6 },
  { label: 'Salta capital', value: 4 },
  { label: 'Tartagal', value: 2 },
  { label: 'Otras', value: 2 },
]

export const sampleFreeTrials = 1
