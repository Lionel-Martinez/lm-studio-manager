<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import Select from 'primevue/select'

import RevenueChart from '@/components/dashboard/RevenueChart.vue'
import BarList from '@/components/ui/BarList.vue'
import SectionBlock from '@/components/ui/SectionBlock.vue'
import { api } from '@/lib/api'
import type { Report } from '@/types/report'
import { formatCurrency, formatShortDate, toISODate } from '@/utils/dates'

type Preset = 'last12' | 'last6' | 'thisYear' | 'lastYear'

const presets: { value: Preset; label: string }[] = [
  { value: 'last12', label: 'Últimos 12 meses' },
  { value: 'last6', label: 'Últimos 6 meses' },
  { value: 'thisYear', label: 'Este año' },
  { value: 'lastYear', label: 'Año pasado' },
]

const preset = ref<Preset>('last12')
const report = ref<Report | null>(null)
const loading = ref(true)
const error = ref('')

/** Rango de fechas (en hora local) de cada opción. */
function range(value: Preset): { from: Date; to: Date } {
  const now = new Date()
  const year = now.getFullYear()

  switch (value) {
    case 'last6':
      return { from: new Date(year, now.getMonth() - 5, 1), to: now }
    case 'thisYear':
      return { from: new Date(year, 0, 1), to: now }
    case 'lastYear':
      return { from: new Date(year - 1, 0, 1), to: new Date(year - 1, 11, 31) }
    default:
      return { from: new Date(year, now.getMonth() - 11, 1), to: now }
  }
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const { from, to } = range(preset.value)
    const { data } = await api.get('/reports', { params: { from: toISODate(from), to: toISODate(to) } })
    report.value = data.data
  } catch {
    error.value = 'No se pudo cargar el informe. Verificá que el backend esté en marcha.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(preset, load)

const monthShort = new Intl.DateTimeFormat('es-AR', { month: 'short' })
const monthLabel = (month: string) => {
  const [y, m] = month.split('-').map(Number)
  const label = monthShort.format(new Date(y!, m! - 1, 1)).replace('.', '')
  return month.endsWith('-01') ? `${label} ${String(y).slice(2)}` : label
}

const revenuePoints = computed(() => (report.value?.revenue_by_month ?? []).map((r) => ({ label: monthLabel(r.month), value: r.value })))
const newClients = computed(() => (report.value?.new_clients_by_month ?? []).map((r) => ({ label: monthLabel(r.month), value: r.value })))
const waitText = computed(() => {
  const days = report.value?.totals.avg_wait_days
  return days == null ? '—' : `${days.toLocaleString('es-AR')} días`
})
</script>

<template>
  <div class="space-y-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <Select v-model="preset" :options="presets" option-label="label" option-value="value" aria-label="Período" class="w-full sm:w-56" />
      <p v-if="loading" class="text-xs text-app-muted" role="status">Calculando…</p>
    </div>

    <p v-if="error" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ error }}</p>

    <template v-if="report">
      <dl class="grid gap-x-8 gap-y-5 border-b border-app-border pb-8 sm:grid-cols-2 lg:grid-cols-5">
        <div>
          <dt class="text-xs text-app-muted">Cobrado</dt>
          <dd class="tabular font-display text-3xl font-medium">{{ formatCurrency(report.totals.collected) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-app-muted">Por cobrar</dt>
          <dd class="tabular font-display text-3xl font-medium">{{ formatCurrency(report.totals.pending) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-app-muted">Vencido sin cobrar</dt>
          <dd class="tabular font-display text-3xl font-medium" :class="report.totals.overdue > 0 ? 'text-app-danger' : ''">{{ formatCurrency(report.totals.overdue) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-app-muted">Proyectos ingresados</dt>
          <dd class="tabular font-display text-3xl font-medium">{{ report.totals.projects }}</dd>
        </div>
        <div>
          <dt class="text-xs text-app-muted">Espera promedio hasta empezar</dt>
          <dd class="tabular font-display text-3xl font-medium">{{ waitText }}</dd>
        </div>
      </dl>

      <SectionBlock title="Ingresos por mes" description="Pagos cobrados en el período, según su fecha de cobro.">
        <RevenueChart :points="revenuePoints" />
      </SectionBlock>

      <div class="grid gap-10 lg:grid-cols-2">
        <SectionBlock title="Ingresos por servicio" description="Los pagos sin proyecto figuran aparte.">
          <BarList :items="report.revenue_by_service" :format="formatCurrency" />
        </SectionBlock>

        <SectionBlock title="Clientes que más pagaron" description="Los 5 primeros del período.">
          <BarList :items="report.top_clients" :format="formatCurrency" />
        </SectionBlock>

        <SectionBlock title="Proyectos por servicio" description="Proyectos que ingresaron en el período.">
          <BarList :items="report.projects_by_service" />
        </SectionBlock>

        <SectionBlock title="Proyectos por estado" description="Estado actual de los que ingresaron en el período.">
          <BarList :items="report.projects_by_status" />
        </SectionBlock>

        <SectionBlock title="Clientes nuevos por mes" description="Según su fecha de primer contacto.">
          <BarList :items="newClients" />
        </SectionBlock>

        <SectionBlock title="Entregas atrasadas" description="Proyectos sin entregar cuya fecha estimada ya pasó (hoy, sin importar el período).">
          <ul v-if="report.late_projects.length" class="divide-y divide-app-border text-sm">
            <li v-for="p in report.late_projects" :key="p.id" class="flex items-baseline justify-between gap-3 py-2">
              <span class="min-w-0">
                <span class="block truncate font-semibold">{{ p.client }}</span>
                <span class="block truncate text-xs text-app-muted">{{ p.project }} · {{ p.status }}</span>
              </span>
              <span class="tabular shrink-0 text-right text-xs text-app-danger">
                {{ p.days_late }} {{ p.days_late === 1 ? 'día' : 'días' }}
                <span class="block text-app-muted">venció el {{ formatShortDate(p.due_date) }}</span>
              </span>
            </li>
          </ul>
          <p v-else class="text-sm text-app-muted">No hay entregas atrasadas.</p>
        </SectionBlock>
      </div>
    </template>
  </div>
</template>
