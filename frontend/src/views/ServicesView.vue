<script setup lang="ts">
import { onMounted, ref } from 'vue'

import EmptyState from '@/components/ui/EmptyState.vue'
import { api } from '@/lib/api'
import type { ServiceOverview } from '@/types/overview'
import { formatCurrency } from '@/utils/dates'

const services = ref<ServiceOverview[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/services')
    services.value = data.data
  } catch {
    error.value = 'No se pudo cargar el resumen de servicios. Verificá que el backend esté en marcha.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-8">
    <p v-if="error" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ error }}</p>

    <EmptyState v-else-if="!loading && services.every((s) => s.projects === 0)" title="Todavía no hay proyectos" description="Cuando cargues proyectos en Clientes, acá vas a ver el resumen de cada tipo de servicio." />

    <div v-else class="grid gap-x-10 gap-y-10 lg:grid-cols-2">
      <section v-for="s in services" :key="s.type" :aria-labelledby="`svc-${s.type}`" class="border-t-2 border-app-rule pt-4">
        <div class="flex items-baseline justify-between gap-3">
          <h2 :id="`svc-${s.type}`" class="font-display text-xl font-medium">{{ s.label }}</h2>
          <p class="text-xs text-app-muted">{{ s.projects }} {{ s.projects === 1 ? 'proyecto' : 'proyectos' }} · {{ s.clients }} {{ s.clients === 1 ? 'cliente' : 'clientes' }}</p>
        </div>

        <ul class="mt-4 grid grid-cols-5 gap-px border-y border-app-border bg-app-border text-center">
          <li v-for="st in s.by_status" :key="st.status" class="bg-app-bg px-1 py-3">
            <p class="tabular font-display text-2xl font-medium">{{ st.count }}</p>
            <p class="text-[11px] leading-tight text-app-muted">{{ st.label }}</p>
          </li>
        </ul>

        <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
          <div>
            <dt class="text-xs text-app-muted">Cobrado</dt>
            <dd class="tabular font-semibold">{{ formatCurrency(s.collected) }}</dd>
          </div>
          <div>
            <dt class="text-xs text-app-muted">Por cobrar</dt>
            <dd class="tabular font-semibold">{{ formatCurrency(s.pending) }}</dd>
          </div>
          <div>
            <dt class="text-xs text-app-muted">Entregas atrasadas</dt>
            <dd class="tabular font-semibold" :class="s.late > 0 ? 'text-app-danger' : ''">{{ s.late }}</dd>
          </div>
        </dl>
      </section>
    </div>
  </div>
</template>
