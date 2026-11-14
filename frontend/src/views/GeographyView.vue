<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import GeographyBreadcrumb from '@/components/geography/GeographyBreadcrumb.vue'
import GeographyStatCard from '@/components/geography/GeographyStatCard.vue'
import GeographyRanking, {
  type GeographyRankingItem,
} from '@/components/geography/GeographyRanking.vue'
import { api } from '@/lib/api'
import type { GeographyOverview, GeoNode } from '@/types/overview'
import { formatCurrency } from '@/utils/dates'

// Ubicación seleccionada durante la navegación (país → provincia → departamento).
const selectedCountry = ref<GeographyRankingItem | null>(null)
const selectedRegion = ref<GeographyRankingItem | null>(null)

const overview = ref<GeographyOverview | null>(null)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/geography')
    overview.value = data.data
  } catch {
    error.value = 'No se pudo cargar la geografía de clientes. Verificá que el backend esté en marcha.'
  }
})

/** Convierte los nodos al formato del ranking; el porcentaje es la parte de clientes dentro del nivel. */
function toItems(nodes: GeoNode[]): GeographyRankingItem[] {
  const total = nodes.reduce((sum, n) => sum + n.clients, 0)
  return nodes.map((n) => ({ id: n.id, name: n.name, clients: n.clients, revenue: n.revenue, percentage: total ? (n.clients / total) * 100 : 0 }))
}

const countries = computed(() => toItems(overview.value?.countries ?? []))
const regions = computed(() => toItems((overview.value?.provinces ?? []).filter((p) => p.country_id === selectedCountry.value?.id)))
const localities = computed(() => toItems((overview.value?.departments ?? []).filter((d) => d.province_id === selectedRegion.value?.id)))

// Determina el nivel geográfico actual.
const currentLevel = computed(() => {
  if (selectedRegion.value) return 'localities'
  if (selectedCountry.value) return 'regions'
  return 'countries'
})

// Construye la ruta de navegación.
const breadcrumbItems = computed(() => {
  const items = []

  if (selectedCountry.value) {
    items.push({
      id: selectedCountry.value.id,
      label: selectedCountry.value.name,
    })
  }

  if (selectedRegion.value) {
    items.push({
      id: selectedRegion.value.id,
      label: selectedRegion.value.name,
    })
  }

  return items
})

// Determina qué ranking mostrar.
const currentItems = computed(() => {
  switch (currentLevel.value) {
    case 'countries':
      return countries.value
    case 'regions':
      return regions.value
    default:
      return localities.value
  }
})

const rankingTitle = computed(() => {
  switch (currentLevel.value) {
    case 'countries':
      return 'Ranking de países'
    case 'regions':
      return `Provincias de ${selectedCountry.value?.name ?? ''}`
    default:
      return `Departamentos de ${selectedRegion.value?.name ?? ''}`
  }
})

// Indicadores del nivel actual.
const stats = computed(() => {
  const items = currentItems.value
  const unlocated = overview.value?.unlocated

  return [
    {
      title: 'Ubicaciones',
      value: items.length,
      description: 'Con al menos un cliente en este nivel.',
    },
    {
      title: 'Clientes',
      value: items.reduce((sum, i) => sum + i.clients, 0),
      description:
        currentLevel.value === 'countries' && unlocated?.clients
          ? `Más ${unlocated.clients} sin ubicación (${formatCurrency(unlocated.revenue)} cobrados).`
          : 'Clientes asociados a esta selección.',
    },
  ]
})

// Al seleccionar un país o región, avanzamos un nivel.
function handleSelect(item: GeographyRankingItem) {
  if (currentLevel.value === 'countries') {
    selectedCountry.value = item
    selectedRegion.value = null
  } else if (currentLevel.value === 'regions') {
    selectedRegion.value = item
  }
}

// Permite regresar desde las migas de pan.
function navigateTo(index: number) {
  if (index === 0) {
    selectedCountry.value = null
    selectedRegion.value = null
  } else if (index === 1) {
    selectedRegion.value = null
  }
}
</script>

<template>
  <div class="space-y-10">
    <p v-if="error" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ error }}</p>
    <GeographyBreadcrumb :items="breadcrumbItems" @navigate="navigateTo" />

    <dl class="grid grid-cols-1 gap-px border-y border-app-border bg-app-border sm:grid-cols-2">
      <GeographyStatCard
        v-for="stat in stats"
        :key="stat.title"
        v-bind="stat"
      />
    </dl>

    <GeographyRanking
      :title="rankingTitle"
      :items="currentItems"
      :clickable="currentLevel !== 'localities'"
      @select="handleSelect"
    />
  </div>
</template>
