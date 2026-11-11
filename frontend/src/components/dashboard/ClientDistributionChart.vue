<script setup lang="ts">
import { computed } from 'vue'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'
import { Doughnut } from 'vue-chartjs'

ChartJS.register(
  ArcElement,
  Tooltip,
  Legend,
)

const hasData = false

const chartData = computed(() => ({
  labels: [],
  datasets: [
    {
      label: 'Clientes',
      data: [],
      backgroundColor: [
        '#10b981',
        '#d6b56d',
        '#3b82f6',
        '#8b5cf6',
        '#f97316',
      ],
      borderWidth: 0,
      hoverOffset: 6,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '70%',
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      callbacks: {
        label: (context: { label: string; parsed: number }) =>
          `${context.label}: ${context.parsed} clientes`,
      },
    },
  },
}
</script>

<template>
  <div class="relative min-h-56">
    <div
      v-if="!hasData"
      class="flex min-h-56 items-center justify-center text-center"
    >
      <div>
        <p class="text-sm font-medium text-app-text">
          Todavía no hay clientes registrados
        </p>
        <p class="mt-1 text-xs text-app-muted">
          Acá aparecerá la distribución por ubicación.
        </p>
      </div>
    </div>

    <div v-else class="flex min-h-56 items-center justify-center">
      <div class="h-56 w-full max-w-64">
        <Doughnut
          :data="chartData"
          :options="chartOptions"
        />
      </div>
    </div>
  </div>
</template>
