<script setup lang="ts">
import { computed } from 'vue'
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  LinearScale,
  Tooltip,
} from 'chart.js'
import { Bar } from 'vue-chartjs'

import EmptyState from '@/components/ui/EmptyState.vue'
import { useChartColors } from '@/composables/useChartColors'
import type { RevenuePoint } from '@/types/dashboard'
import { formatCompactCurrency, formatCurrency } from '@/utils/dates'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip)

const props = defineProps<{
  points: RevenuePoint[]
}>()

const colors = useChartColors()

const hasData = computed(() => props.points.some((p) => p.value > 0))

const chartData = computed(() => ({
  labels: props.points.map((p) => p.label),
  datasets: [
    {
      label: 'Ingresos',
      data: props.points.map((p) => p.value),
      // El último punto es el mes actual y se resalta.
      backgroundColor: props.points.map((_, i) =>
        i === props.points.length - 1 ? colors.value.accent : colors.value.bar,
      ),
      borderRadius: 0,
      maxBarThickness: 44,
    },
  ],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: colors.value.text,
      titleColor: colors.value.surface,
      bodyColor: colors.value.surface,
      cornerRadius: 2,
      displayColors: false,
      callbacks: {
        label: (context: { parsed: { y: number | null } }) =>
          formatCurrency(context.parsed.y ?? 0),
      },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      border: { display: false },
      grid: { color: colors.value.border },
      ticks: {
        color: colors.value.muted,
        maxTicksLimit: 4,
        callback: (value: string | number) => formatCompactCurrency(Number(value)),
      },
    },
    x: {
      border: { color: colors.value.text },
      grid: { display: false },
      ticks: { color: colors.value.muted },
    },
  },
}))
</script>

<template>
  <EmptyState
    v-if="!hasData"
    title="Todavía no hay ingresos"
    description="Los ingresos mensuales aparecen cuando registres pagos."
  />

  <div v-else class="h-56 border-t-2 border-app-rule pt-4">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>
