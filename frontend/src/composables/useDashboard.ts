import { computed } from 'vue'

import {
  sampleDistribution,
  sampleFreeTrials,
  samplePayments,
  sampleProjects,
  sampleRevenue,
} from '@/data/sampleDashboard'
import { parseISODate, startOfDay } from '@/utils/dates'

/**
 * Fuente de datos del dashboard.
 * Por ahora devuelve datos de ejemplo; en la Etapa 2 se reemplaza por
 * llamadas a la API de Laravel sin tocar los componentes que la consumen.
 */
export function useDashboard() {
  const isSample = true

  const projects = sampleProjects
  const payments = samplePayments
  const revenue = sampleRevenue
  const distribution = sampleDistribution

  const waitingList = computed(() =>
    projects.filter((p) => p.status === 'waiting' || p.status === 'prospect'),
  )

  const kpis = computed(() => {
    const today = startOfDay(new Date())
    const overdue = payments.filter((p) => parseISODate(p.dueDate) < today)
    const activeClients = new Set(
      projects.filter((p) => p.status === 'in_progress' || p.status === 'ready').map((p) => p.client),
    )

    return {
      monthlyRevenue: revenue.at(-1)?.value ?? 0,
      activeClients: activeClients.size,
      prospects: waitingList.value.length,
      pendingPayments: payments.length - overdue.length,
      overdueCount: overdue.length,
      overdueAmount: overdue.reduce((sum, p) => sum + p.amount, 0),
      freeTrials: sampleFreeTrials,
    }
  })

  return { isSample, projects, payments, revenue, distribution, waitingList, kpis }
}
