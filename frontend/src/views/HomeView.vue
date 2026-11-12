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
import { formatCurrency } from '@/utils/dates'

const { isSample, projects, payments, revenue, distribution, waitingList, kpis } = useDashboard()

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
    label: 'Pruebas gratuitas',
    value: String(kpis.value.freeTrials),
    note: 'Períodos de prueba activos',
  },
])
</script>

<template>
  <div class="space-y-10">
    <p v-if="isSample" class="text-xs text-app-muted">
      Datos de ejemplo: se reemplazan por los reales cuando se conecte la base de datos.
    </p>

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

      <SectionBlock title="Distribución de clientes" description="Por localidad.">
        <ClientDistributionChart :points="distribution" />
      </SectionBlock>
    </div>
  </div>
</template>
