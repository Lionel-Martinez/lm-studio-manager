<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import axios from 'axios'

import FormField from '@/components/ui/FormField.vue'
import { getServer, normalizeServerUrl, setServer, setToken } from '@/lib/nativeSession'

const router = useRouter()
const address = ref(getServer() ?? '')
const error = ref('')
const loading = ref(false)

async function save() {
  error.value = ''
  const url = normalizeServerUrl(address.value)

  if (!url) {
    error.value = 'Ingresá una dirección https válida, por ejemplo https://mi-pc.tu-red.ts.net'
    return
  }

  loading.value = true

  try {
    // Sin token, la API responde 401: eso confirma que es el servidor correcto y que está en marcha.
    await axios.get(`${url}/api/app/user`, { timeout: 8000, headers: { Accept: 'application/json' } })
    error.value = 'Esa dirección respondió, pero no parece ser el servidor de LM Studio.'
  } catch (e) {
    if (axios.isAxiosError(e) && e.response?.status === 401) {
      if (getServer() !== url) setToken(null)
      setServer(url)
      await router.replace({ name: 'login' })
    } else {
      error.value = 'No se pudo conectar. Verificá que la PC esté encendida, que Tailscale esté activo en este dispositivo y que la dirección sea correcta.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-app-bg px-5 py-10 text-app-text">
    <div class="w-full max-w-sm">
      <p class="font-display text-3xl font-semibold tracking-tight">LM Studio</p>
      <p class="mt-1 text-sm text-app-muted">Conectá esta app con tu servidor.</p>

      <form class="mt-10 space-y-5" novalidate @submit.prevent="save">
        <p v-if="error" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
          {{ error }}
        </p>

        <FormField label="Dirección del servidor" for="server-url" hint="La que te dio «tailscale serve», con https://.">
          <InputText id="server-url" v-model="address" type="url" inputmode="url" autocapitalize="off" autocomplete="off" autofocus fluid />
        </FormField>

        <Button type="submit" label="Conectar" :loading="loading" fluid />
      </form>
    </div>
  </main>
</template>
