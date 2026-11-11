<script setup lang="ts">
import { computed } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler,
} from 'chart.js'
import { Line } from 'vue-chartjs'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler,
)

const hasData = false

const chartData = computed(() => ({
  labels: [],
  datasets: [
    {
      label: 'Ingresos',
      data: [],
      borderColor: '#10b981',
      backgroundColor: 'rgba(16, 185, 129, 0.10)',
      fill: true,
      tension: 0.4,
      pointRadius: 4,
      pointHoverRadius: 6,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      callbacks: {
        label: (context: { parsed: { y: number | null } }) =>
          `Ingresos: $${(context.parsed.y ?? 0).toLocaleString('es-AR')}`,
      },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(148, 163, 184, 0.10)',
      },
      ticks: {
        callback: (value: string | number) =>
          `$${Number(value).toLocaleString('es-AR')}`,
      },
    },
    x: {
      grid: {
        display: false,
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
          Todavía no hay datos financieros
        </p>
        <p class="mt-1 text-xs text-app-muted">
          Los ingresos aparecerán cuando registres pagos.
        </p>
      </div>
    </div>

    <Line
      v-else
      :data="chartData"
      :options="chartOptions"
    />
  </div>
</template>
