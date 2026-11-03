<script setup lang="ts">
import { RouterLink } from 'vue-router'
import {
  LayoutDashboard,
  Users,
  Layers3,
  Wallet,
  ChartNoAxesCombined,
  MapPinned,
  Settings,
  ChevronLeft,
  Code2,
} from 'lucide-vue-next'

const navigation = [
  {
    title: 'GENERAL',
    items: [
      { name: 'Dashboard', path: '/', icon: LayoutDashboard },
    ],
  },
  {
    title: 'GESTIÓN COMERCIAL',
    items: [
      { name: 'Clientes', path: '/clientes', icon: Users },
      { name: 'Servicios', path: '/servicios', icon: Layers3 },
      { name: 'Pagos', path: '/pagos', icon: Wallet },
      { name: 'Reportes', path: '/reportes', icon: ChartNoAxesCombined },
    ],
  },
  {
    title: 'ANÁLISIS',
    items: [
      { name: 'Geografía', path: '/geografia', icon: MapPinned },
    ],
  },
]
</script>

<template>
  <aside class="flex h-screen w-64 shrink-0 flex-col border-r border-app-border bg-app-surface">
    <!-- Marca -->
    <div class="flex h-20 items-center gap-3 border-b border-app-border px-6">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
        <Code2 :size="23" />
      </div>

      <div>
        <h1 class="text-base font-bold tracking-tight text-app-text">LM Studio</h1>
        <p class="text-xs text-app-muted">Client Manager</p>
      </div>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 space-y-8 overflow-y-auto px-4 py-7">
      <div v-for="group in navigation" :key="group.title">
        <h2 class="mb-3 px-3 text-[10px] font-bold tracking-[0.15em] text-app-muted">
          {{ group.title }}
        </h2>

        <ul class="space-y-1">
          <li v-for="item in group.items" :key="item.path">
            <RouterLink
              :to="item.path"
              class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-app-muted transition-colors hover:bg-app-elevated hover:text-app-text"
              active-class="!bg-brand-500/10 !text-brand-500"
            >
              <component :is="item.icon" :size="19" class="shrink-0" />
              <span>{{ item.name }}</span>
            </RouterLink>
          </li>
        </ul>
      </div>
    </nav>

    <!-- Configuración -->
    <div class="border-t border-app-border p-4">
      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-app-muted transition hover:bg-app-elevated hover:text-app-text"
      >
        <Settings :size="19" />
        Configuración
      </button>

      <div class="mt-4 flex items-center gap-3 rounded-xl bg-app-elevated p-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500/15 text-sm font-bold text-brand-500">
          LM
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-app-text">Administrador</p>
          <p class="truncate text-xs text-app-muted">LM Studio</p>
        </div>

        <ChevronLeft :size="16" class="rotate-180 text-app-muted" />
      </div>
    </div>
  </aside>
</template>
