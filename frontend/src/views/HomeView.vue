<script setup lang="ts">
import { computed } from 'vue'

import ClientDistributionChart from '@/components/dashboard/ClientDistributionChart.vue'
import DeliveryCalendar from '@/components/dashboard/DeliveryCalendar.vue'
import KpiStrip, { type KpiItem } from '@/components/dashboard/KpiStrip.vue'
import RevenueChart from '@/components/dashboard/RevenueChart.vue'
import UpcomingPayments from '@/components/dashboard/UpcomingPayments.vue'
import WaitingList from '@/components/dashboard/WaitingList.vue'
import SectionBlock from '@/components/ui/SectionBlock.vue'
import { useDashboard } from '@/composables/useDashboard'
import { addDays, formatCurrency, parseISODate, startOfDay } from '@/utils/dates'

const { loading, error, projects, payments, revenue, distribution, waitingList, kpis } = useDashboard()

/** Proyectos sin entregar cuya fecha de entrega cae en los próximos 14 días. */
const dueSoon = computed(() => {
  const today = startOfDay(new Date())
  const limit = addDays(today, 14)

  return projects.value.filter((p) => {
    if (!p.dueDate) return false
    const due = parseISODate(p.dueDate)
    return due >= today && due <= limit
  }).length
})

const kpiItems = computed<KpiItem[]>(() => [
  {
    label: 'Ingresos del mes',
    value: formatCurrency(kpis.value.monthlyRevenue),
    note: 'Pagos acreditados en el mes',
  },
  {
    label: 'Clientes activos',
    value: String(kpis.value.activeClients),
    note: 'Con servicios en curso',
  },
  {
    label: 'Clientes potenciales',
    value: String(kpis.value.prospects),
    note: 'En seguimiento o en espera',
  },
  {
    label: 'Pagos pendientes',
    value: String(kpis.value.pendingPayments),
    note: 'Todavía sin acreditar',
  },
  {
    label: 'Cuotas vencidas',
    value: String(kpis.value.overdueCount),
    note: kpis.value.overdueCount > 0 ? `Suman ${formatCurrency(kpis.value.overdueAmount)}` : 'Al día',
    tone: kpis.value.overdueCount > 0 ? 'danger' : 'default',
  },
  {
    label: 'Entregas próximas',
    value: String(dueSoon.value),
    note: 'En los próximos 14 días',
  },
])
</script>

<template>
  <div class="space-y-10">
    <p v-if="error" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
      {{ error }}
    </p>

    <p v-else-if="loading" class="text-xs text-app-muted" role="status">Cargando resumen…</p>

    <KpiStrip :items="kpiItems" />

    <SectionBlock
      title="Calendario de entregas"
      description="Cuándo entra cada cliente y cuándo se entrega su proyecto."
    >
      <DeliveryCalendar :projects="projects" />
    </SectionBlock>

    <div class="grid gap-10 xl:grid-cols-2">
      <SectionBlock title="Cobros próximos" description="Ordenados por fecha de vencimiento.">
        <UpcomingPayments :payments="payments" />
      </SectionBlock>

      <SectionBlock title="Lista de espera" description="Clientes que todavía no empezaron.">
        <WaitingList :items="waitingList" />
      </SectionBlock>
    </div>

    <div class="grid gap-10 xl:grid-cols-2">
      <SectionBlock title="Evolución de ingresos" description="Últimos seis meses.">
        <RevenueChart :points="revenue" />
      </SectionBlock>

      <SectionBlock title="Distribución de clientes" description="Por departamento o provincia.">
        <ClientDistributionChart :points="distribution" />
      </SectionBlock>
    </div>
  </div>
</template>
