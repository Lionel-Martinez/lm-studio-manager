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

/** 'credentials' pide correo y contraseña; 'code' pide el segundo paso. */
const step = ref<'credentials' | 'code'>('credentials')
const code = ref('')
const useRecovery = ref(false)

function redirect() {
  const target = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/'
  return router.replace(target)
}

async function submit() {
  error.value = ''

  if (!email.value.trim() || !password.value) {
    error.value = 'Ingresá tu correo y tu contraseña.'
    return
  }

  loading.value = true

  try {
    const result = await auth.login(email.value.trim(), password.value)

    if (result === 'two_factor') {
      step.value = 'code'
      return
    }

    await redirect()
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

async function submitCode() {
  error.value = ''

  if (!code.value.trim()) {
    error.value = useRecovery.value ? 'Ingresá un código de recuperación.' : 'Ingresá el código de 6 dígitos.'
    return
  }

  loading.value = true

  try {
    await auth.verifyTwoFactor(useRecovery.value ? { recovery_code: code.value.trim() } : { code: code.value.trim() })
    await redirect()
  } catch (e) {
    const status = getStatus(e)
    const validation = getValidationErrors(e)

    // Venció o se bloqueó: hay que volver a empezar con la contraseña.
    if (status === 401 || status === 429) {
      step.value = 'credentials'
      error.value = (e as { response?: { data?: { errors?: { code?: string[] } } } }).response?.data?.errors?.code?.[0] ?? 'Volvé a ingresar tu correo y contraseña.'
    } else {
      error.value = validation?.code?.[0] ?? 'No se pudo verificar el código. Intentá de nuevo.'
    }
  } finally {
    code.value = ''
    loading.value = false
  }
}

function backToCredentials() {
  step.value = 'credentials'
  error.value = ''
  code.value = ''
  useRecovery.value = false
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-app-bg px-5 py-10 text-app-text">
    <div class="w-full max-w-sm">
      <p class="font-display text-3xl font-semibold tracking-tight">LM Studio</p>
      <p class="mt-1 text-sm text-app-muted">Cuaderno de clientes. Acceso privado.</p>

      <form v-if="step === 'credentials'" class="mt-10 space-y-5" novalidate @submit.prevent="submit">
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

      <form v-else class="mt-10 space-y-5" novalidate @submit.prevent="submitCode">
        <p v-if="error" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
          {{ error }}
        </p>

        <p class="text-sm text-app-muted">
          {{ useRecovery ? 'Ingresá uno de tus códigos de recuperación. Cada uno sirve una sola vez.' : 'Abrí tu app de verificación e ingresá el código de 6 dígitos.' }}
        </p>

        <FormField :label="useRecovery ? 'Código de recuperación' : 'Código de verificación'" for="login-code">
          <InputText
            id="login-code"
            v-model="code"
            :inputmode="useRecovery ? 'text' : 'numeric'"
            :autocomplete="useRecovery ? 'off' : 'one-time-code'"
            :maxlength="useRecovery ? 30 : 7"
            autofocus
            fluid
          />
        </FormField>

        <Button type="submit" label="Verificar" :loading="loading" fluid />

        <div class="flex justify-between text-xs text-app-muted">
          <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-text" @click="useRecovery = !useRecovery; code = ''; error = ''">
            {{ useRecovery ? 'Usar el código de la app' : 'Usar un código de recuperación' }}
          </button>
          <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-text" @click="backToCredentials">Volver</button>
        </div>
      </form>
    </div>
  </main>
</template>
