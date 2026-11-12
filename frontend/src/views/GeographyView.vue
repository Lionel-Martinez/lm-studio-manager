<script setup lang="ts">
import { computed, ref } from 'vue'
import GeographyBreadcrumb from '@/components/geography/GeographyBreadcrumb.vue'
import GeographyStatCard from '@/components/geography/GeographyStatCard.vue'
import GeographyRanking, {
  type GeographyRankingItem,
} from '@/components/geography/GeographyRanking.vue'

// Ubicación seleccionada durante la navegación.
const selectedCountry = ref<GeographyRankingItem | null>(null)
const selectedRegion = ref<GeographyRankingItem | null>(null)

// Estos arreglos recibirán los datos de Laravel más adelante.
const countries = ref<GeographyRankingItem[]>([])
const regions = ref<GeographyRankingItem[]>([])
const localities = ref<GeographyRankingItem[]>([])

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
      return `Regiones de ${selectedCountry.value?.name ?? ''}`
    default:
      return `Localidades de ${selectedRegion.value?.name ?? ''}`
  }
})

// Indicadores del nivel actual.
const stats = computed(() => [
  {
    title: 'Ubicaciones',
    value: '—',
    description: 'Se calculará con los datos registrados.',
  },
  {
    title: 'Clientes',
    value: '—',
    description: 'Clientes asociados a esta selección.',
  },
])

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
