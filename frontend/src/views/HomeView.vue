<script setup lang="ts">
import {
  Wallet,
  Users,
  UserRoundSearch,
  Clock,
  AlertCircle,
  Gift,
} from 'lucide-vue-next'

import StatCard from '@/components/dashboard/StatCard.vue'
import RevenueChart from '@/components/dashboard/RevenueChart.vue'
import ClientDistributionChart from '@/components/dashboard/ClientDistributionChart.vue'

const stats = [
  {
    title: 'Ingresos del mes',
    value: '—',
    description: 'Sin datos registrados todavía',
    icon: Wallet,
    accent: 'green' as const,
  },
  {
    title: 'Clientes activos',
    value: '0',
    description: 'Clientes con servicios activos',
    icon: Users,
    accent: 'blue' as const,
  },
  {
    title: 'Clientes potenciales',
    value: '0',
    description: 'Contactos en seguimiento comercial',
    icon: UserRoundSearch,
    accent: 'gold' as const,
  },
  {
    title: 'Pagos pendientes',
    value: '0',
    description: 'Pagos pendientes de acreditación',
    icon: Clock,
    accent: 'gold' as const,
  },
  {
    title: 'Cuotas vencidas',
    value: '0',
    description: 'Cuotas que requieren seguimiento',
    icon: AlertCircle,
    accent: 'red' as const,
  },
  {
    title: 'Pruebas gratuitas',
    value: '0',
    description: 'Períodos de prueba actualmente activos',
    icon: Gift,
    accent: 'green' as const,
  },
]
</script>

<template>
  <div class="space-y-7">
    <section>
      <h2 class="text-lg font-semibold text-app-text">
        Resumen general
      </h2>

      <p class="mt-1 text-sm text-app-muted">
        Acá podés consultar el estado comercial y financiero de LM Studio.
      </p>
    </section>

    <section>
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-app-text">
          Indicadores principales
        </h3>

        <span class="rounded-md border border-app-border bg-app-elevated px-2.5 py-1 text-xs text-app-muted">
          Datos iniciales
        </span>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <StatCard
          v-for="stat in stats"
          :key="stat.title"
          v-bind="stat"
        />
      </div>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <article class="rounded-xl border border-app-border bg-app-surface p-5">
        <div class="mb-4">
          <h3 class="font-semibold text-app-text">
            Evolución de ingresos
          </h3>

          <p class="mt-1 text-sm text-app-muted">
            Seguimiento mensual de los ingresos de LM Studio.
          </p>
        </div>

        <RevenueChart />
      </article>

    <article class="rounded-xl border border-app-border bg-app-surface p-5">
        <div class="mb-4">
          <h3 class="font-semibold text-app-text">Distribución de clientes</h3>
          <p class="mt-1 text-sm text-app-muted">
            Países, provincias y localidades con mayor presencia.
          </p>
      </div>

    <ClientDistributionChart />
    </article>
    </section>
  </div>
</template>
