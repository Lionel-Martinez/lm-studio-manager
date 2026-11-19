<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'

import SectionBlock from '@/components/ui/SectionBlock.vue'
import { api } from '@/lib/api'

interface Device {
  id: number
  name: string
  platform: string | null
  last_used_at: string | null
  created_at: string
  current: boolean
}

const devices = ref<Device[]>([])
const error = ref('')
const busy = ref<number | null>(null)

const platforms: Record<string, string> = { linux: 'Linux', android: 'Android', ios: 'iOS', other: 'Otro' }
const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }) : 'Nunca')

async function load() {
  try {
    devices.value = (await api.get('/account/devices')).data.data
  } catch {
    error.value = 'No se pudieron cargar los dispositivos.'
  }
}

async function revoke(device: Device) {
  if (!window.confirm(`¿Cerrar la sesión de «${device.name}»? Tendrá que volver a iniciar sesión.`)) return
  busy.value = device.id
  error.value = ''

  try {
    await api.delete(`/account/devices/${device.id}`)
    devices.value = devices.value.filter((d) => d.id !== device.id)
  } catch {
    error.value = 'No se pudo cerrar esa sesión.'
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>

<template>
  <SectionBlock title="Dispositivos" description="Apps de escritorio y celular con la sesión abierta.">
    <p v-if="error" class="mb-2 text-sm text-app-danger" role="alert">{{ error }}</p>
    <p v-if="!error && devices.length === 0" class="text-sm text-app-muted">No hay dispositivos con sesión abierta.</p>

    <ul class="divide-y divide-app-border border-y border-app-border">
      <li v-for="d in devices" :key="d.id" class="flex items-center justify-between gap-3 py-2.5 text-sm">
        <div class="min-w-0">
          <p class="truncate font-medium">
            {{ d.name }}
            <span v-if="d.current" class="ml-1 text-xs font-normal text-app-muted">(este dispositivo)</span>
          </p>
          <p class="text-xs text-app-muted">{{ platforms[d.platform ?? 'other'] ?? 'Otro' }} · último uso {{ fmt(d.last_used_at) }}</p>
        </div>
        <Button v-if="!d.current" label="Cerrar sesión" size="small" severity="secondary" text :loading="busy === d.id" @click="revoke(d)" />
      </li>
    </ul>
  </SectionBlock>
</template>
