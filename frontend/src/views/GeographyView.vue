<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  Globe2,
  MapPinned,
  MapPin,
  Users,
} from 'lucide-vue-next'

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
    case 'localities':
      return localities.value
  }
})

const rankingTitle = computed(() => {
  switch (currentLevel.value) {
    case 'countries':
      return 'Ranking de países'
    case 'regions':
      return `Regiones de ${selectedCountry.value?.name ?? ''}`
    case 'localities':
      return `Localidades de ${selectedRegion.value?.name ?? ''}`
  }
})

// Indicadores del nivel actual.
const stats = computed(() => [
  {
    title: 'Ubicaciones',
    value: '—',
    description: 'Se calculará con los datos registrados.',
    icon: currentLevel.value === 'countries'
      ? Globe2
      : currentLevel.value === 'regions'
        ? MapPinned
        : MapPin,
  },
  {
    title: 'Clientes',
    value: '—',
    description: 'Clientes asociados a esta selección.',
    icon: Users,
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
  <div class="space-y-7">
    <section>
      <h2 class="text-lg font-semibold text-app-text">
        Análisis geográfico
      </h2>
      <p class="mt-1 text-sm text-app-muted">
        Conocé dónde están tus clientes y cómo se distribuyen tus ingresos.
      </p>
    </section>

    <section class="rounded-xl border border-app-border bg-app-surface p-5">
      <GeographyBreadcrumb
        :items="breadcrumbItems"
        @navigate="navigateTo"
      />
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <GeographyStatCard
        v-for="stat in stats"
        :key="stat.title"
        v-bind="stat"
      />
    </section>

    <section>
      <GeographyRanking
        :title="rankingTitle"
        :items="currentItems"
        :clickable="currentLevel !== 'localities'"
        @select="handleSelect"
      />
    </section>
  </div>
</template>
