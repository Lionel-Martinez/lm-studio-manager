<script setup lang="ts">
import { RouterLink } from 'vue-router'

import { useTheme } from '@/composables/useTheme'

const navigation = [
  { name: 'Resumen', path: '/' },
  { name: 'Clientes', path: '/clientes' },
  { name: 'Servicios', path: '/servicios' },
  { name: 'Pagos', path: '/pagos' },
  { name: 'Reportes', path: '/reportes' },
  { name: 'Geografía', path: '/geografia' },
]

const linkBase =
  'block whitespace-nowrap border-b-2 px-1 py-2 text-sm transition-colors lg:border-b-0 lg:border-l-2 lg:py-1.5 lg:pl-3 lg:pr-0'
const linkActive = 'border-app-accent font-semibold text-app-text'
const linkIdle = 'border-transparent text-app-muted hover:text-app-text'

const { isDark, setTheme } = useTheme()
</script>

<template>
  <aside
    class="flex flex-col gap-5 border-b border-app-border bg-app-sunken px-5 py-5 lg:sticky lg:top-0 lg:h-screen lg:w-60 lg:shrink-0 lg:gap-10 lg:border-b-0 lg:border-r lg:px-7 lg:py-9"
  >
    <div class="flex items-baseline justify-between gap-3 lg:block">
      <p class="font-display text-2xl font-semibold tracking-tight">LM Studio</p>
      <p class="text-xs text-app-muted lg:mt-0.5">Cuaderno de clientes</p>
    </div>

    <nav aria-label="Principal" class="-mx-1 flex gap-1 overflow-x-auto px-1 lg:mx-0 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:px-0">
      <RouterLink
        v-for="item in navigation"
        :key="item.path"
        :to="item.path"
        custom
        v-slot="{ href, navigate, isActive, isExactActive }"
      >
        <a
          :href="href"
          :aria-current="(item.path === '/' ? isExactActive : isActive) ? 'page' : undefined"
          :class="[linkBase, (item.path === '/' ? isExactActive : isActive) ? linkActive : linkIdle]"
          @click="navigate"
        >
          {{ item.name }}
        </a>
      </RouterLink>
    </nav>

    <div class="flex items-center gap-2 text-xs text-app-muted lg:mt-auto">
      <span>Tema</span>
      <button
        type="button"
        class="cursor-pointer underline-offset-4 transition-colors hover:text-app-text"
        :class="!isDark ? 'font-semibold text-app-text underline' : ''"
        :aria-pressed="!isDark"
        @click="setTheme('light')"
      >
        Claro
      </button>
      <span aria-hidden="true">·</span>
      <button
        type="button"
        class="cursor-pointer underline-offset-4 transition-colors hover:text-app-text"
        :class="isDark ? 'font-semibold text-app-text underline' : ''"
        :aria-pressed="isDark"
        @click="setTheme('dark')"
      >
        Oscuro
      </button>
    </div>
  </aside>
</template>
