import { computed, onMounted, ref } from 'vue'

import { api } from '@/lib/api'
import type {
  ClientDistributionPoint,
  DeliveryItem,
  PendingPayment,
  ProjectStatus,
  RevenuePoint,
} from '@/types/dashboard'
import { parseISODate } from '@/utils/dates'

interface ApiProject {
  id: number
  client: string
  service: string
  name: string | null
  status: ProjectStatus
  entry_date: string
  start_date: string | null
  due_date: string | null
  delay_reason: string | null
}

interface ApiPayment {
  id: number
  client: string
  concept: string
  due_date: string
  amount: number
}

interface ApiDashboard {
  kpis: {
    monthly_revenue: number
    active_clients: number
    prospects: number
    pending_payments: number
    overdue_count: number
    overdue_amount: number
  }
  projects: ApiProject[]
  payments: ApiPayment[]
  revenue: { month: string; value: number }[]
  distribution: ClientDistributionPoint[]
}

const monthFormatter = new Intl.DateTimeFormat('es-AR', { month: 'short' })

/** Fuente de datos del resumen: una sola llamada a `/api/dashboard`. */
export function useDashboard() {
  const loading = ref(true)
  const error = ref<string | null>(null)
  const raw = ref<ApiDashboard | null>(null)

  async function load() {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get('/dashboard')
      raw.value = data.data
    } catch {
      error.value = 'No se pudo cargar el resumen. Verificá que el backend esté en marcha.'
    } finally {
      loading.value = false
    }
  }

  onMounted(load)

  const projects = computed<DeliveryItem[]>(() =>
    (raw.value?.projects ?? []).map((p) => ({
      id: p.id,
      client: p.client,
      service: p.name || p.service,
      status: p.status,
      entryDate: p.entry_date,
      startDate: p.start_date,
      dueDate: p.due_date,
      delayReason: p.delay_reason ?? undefined,
    })),
  )

  const payments = computed<PendingPayment[]>(() =>
    (raw.value?.payments ?? []).map((p) => ({
      id: p.id,
      client: p.client,
      concept: p.concept,
      dueDate: p.due_date,
      amount: p.amount,
    })),
  )

  const revenue = computed<RevenuePoint[]>(() =>
    (raw.value?.revenue ?? []).map((r) => ({
      label: monthFormatter.format(parseISODate(`${r.month}-01`)).replace('.', ''),
      value: r.value,
    })),
  )

  const distribution = computed(() => raw.value?.distribution ?? [])

  const waitingList = computed(() =>
    projects.value.filter((p) => p.status === 'waiting' || p.status === 'prospect'),
  )

  const kpis = computed(() => ({
    monthlyRevenue: raw.value?.kpis.monthly_revenue ?? 0,
    activeClients: raw.value?.kpis.active_clients ?? 0,
    prospects: raw.value?.kpis.prospects ?? 0,
    pendingPayments: raw.value?.kpis.pending_payments ?? 0,
    overdueCount: raw.value?.kpis.overdue_count ?? 0,
    overdueAmount: raw.value?.kpis.overdue_amount ?? 0,
  }))

  return { loading, error, projects, payments, revenue, distribution, waitingList, kpis, reload: load }
}
