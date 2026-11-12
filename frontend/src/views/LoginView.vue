<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'

import FormField from '@/components/ui/FormField.vue'
import { getStatus, getValidationErrors } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''

  if (!email.value.trim() || !password.value) {
    error.value = 'Ingresá tu correo y tu contraseña.'
    return
  }

  loading.value = true

  try {
    await auth.login(email.value.trim(), password.value)
    const target = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/'
    await router.replace(target)
  } catch (e) {
    const validation = getValidationErrors(e)
    if (validation?.email?.[0]) error.value = validation.email[0]
    else if (getStatus(e) === 429) error.value = 'Demasiados intentos. Esperá unos minutos e intentá de nuevo.'
    else error.value = 'No se pudo iniciar sesión. Verificá que el backend esté en marcha.'
  } finally {
    password.value = ''
    loading.value = false
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-app-bg px-5 py-10 text-app-text">
    <div class="w-full max-w-sm">
      <p class="font-display text-3xl font-semibold tracking-tight">LM Studio</p>
      <p class="mt-1 text-sm text-app-muted">Cuaderno de clientes. Acceso privado.</p>

      <form class="mt-10 space-y-5" novalidate @submit.prevent="submit">
        <p v-if="error" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
          {{ error }}
        </p>

        <FormField label="Correo" for="login-email">
          <InputText id="login-email" v-model="email" type="email" autocomplete="username" autofocus fluid />
        </FormField>

        <FormField label="Contraseña" for="login-password">
          <Password
            v-model="password"
            input-id="login-password"
            :feedback="false"
            toggle-mask
            autocomplete="current-password"
            fluid
          />
        </FormField>

        <Button type="submit" label="Entrar" :loading="loading" fluid />
      </form>
    </div>
  </main>
</template>
