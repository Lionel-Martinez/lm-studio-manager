<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import QRCode from 'qrcode'

import FormField from '@/components/ui/FormField.vue'
import SectionBlock from '@/components/ui/SectionBlock.vue'
import { api, getValidationErrors, type FieldErrors } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const enabled = computed(() => auth.user?.two_factor_enabled === true)

type Mode = 'idle' | 'setup' | 'recovery' | 'disable' | 'renew'
const mode = ref<Mode>('idle')

const secret = ref('')
const qr = ref('')
const code = ref('')
const password = ref('')
const recoveryCodes = ref<string[]>([])
const errors = ref<FieldErrors>({})
const busy = ref(false)
const copied = ref(false)

const firstError = (field: string) => errors.value[field]?.[0]
const groupedSecret = computed(() => secret.value.match(/.{1,4}/g)?.join(' ') ?? '')

function reset() {
  mode.value = 'idle'
  secret.value = ''
  qr.value = ''
  code.value = ''
  password.value = ''
  errors.value = {}
  copied.value = false
}

async function guarded(action: () => Promise<void>) {
  errors.value = {}
  busy.value = true

  try {
    await action()
  } catch (e) {
    errors.value = getValidationErrors(e) ?? { code: ['No se pudo completar la acción. Intentá de nuevo.'] }
  } finally {
    busy.value = false
  }
}

const startSetup = () =>
  guarded(async () => {
    const { data } = await api.post('/account/two-factor/setup')
    secret.value = data.data.secret
    // El código QR se dibuja en este navegador: la clave no pasa por ningún servicio externo.
    qr.value = await QRCode.toDataURL(data.data.uri, { margin: 1, width: 192 })
    mode.value = 'setup'
  })

const confirmSetup = () =>
  guarded(async () => {
    const { data } = await api.post('/account/two-factor/confirm', { password: password.value, code: code.value })
    recoveryCodes.value = data.data.recovery_codes
    await auth.fetchUser()
    reset()
    mode.value = 'recovery'
  })

const renewCodes = () =>
  guarded(async () => {
    const { data } = await api.post('/account/two-factor/recovery-codes', { password: password.value })
    recoveryCodes.value = data.data.recovery_codes
    reset()
    mode.value = 'recovery'
  })

const disable = () =>
  guarded(async () => {
    await api.delete('/account/two-factor', { data: { password: password.value } })
    await auth.fetchUser()
    reset()
  })

async function copyCodes() {
  try {
    await navigator.clipboard.writeText(recoveryCodes.value.join('\n'))
    copied.value = true
  } catch {
    copied.value = false
  }
}

function finishRecovery() {
  recoveryCodes.value = []
  mode.value = 'idle'
}
</script>

<template>
  <SectionBlock title="Verificación en dos pasos" description="Además de la contraseña, pide un código de tu teléfono al entrar.">
    <!-- Códigos de recuperación (se muestran una sola vez) -->
    <div v-if="mode === 'recovery'" class="max-w-sm space-y-4">
      <p class="border-l-2 border-app-accent bg-app-accent-soft px-3 py-2 text-sm">
        Guardá estos códigos en un lugar seguro (por ejemplo, un gestor de contraseñas). Si perdés el teléfono, son la única forma de entrar. <strong>No se vuelven a mostrar.</strong>
      </p>
      <ul class="tabular grid grid-cols-2 gap-x-6 gap-y-1 border border-app-border bg-app-surface p-4 font-mono text-sm">
        <li v-for="c in recoveryCodes" :key="c">{{ c }}</li>
      </ul>
      <div class="flex gap-2">
        <Button label="Copiar" severity="secondary" size="small" @click="copyCodes" />
        <Button label="Ya los guardé" size="small" @click="finishRecovery" />
      </div>
      <p v-if="copied" class="text-xs text-app-muted" role="status">Copiados al portapapeles.</p>
    </div>

    <!-- Alta: QR + código -->
    <form v-else-if="mode === 'setup'" class="max-w-sm space-y-4" novalidate @submit.prevent="confirmSetup">
      <ol class="list-decimal space-y-1 pl-5 text-sm text-app-muted">
        <li>Instalá una app como Google Authenticator, Authy o 1Password.</li>
        <li>Escaneá este código o escribí la clave a mano.</li>
        <li>Ingresá el código de 6 dígitos que muestra la app.</li>
      </ol>

      <img :src="qr" alt="Código QR para tu app de verificación" width="192" height="192" class="border border-app-border bg-white" />
      <p class="text-xs text-app-muted">Clave manual: <span class="tabular select-all font-mono text-app-text">{{ groupedSecret }}</span></p>

      <FormField label="Código de la app" for="tf-code" :error="firstError('code')">
        <InputText id="tf-code" v-model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" fluid />
      </FormField>

      <FormField label="Tu contraseña" for="tf-pass" :error="firstError('password')">
        <Password v-model="password" input-id="tf-pass" :feedback="false" toggle-mask autocomplete="current-password" fluid />
      </FormField>

      <div class="flex gap-2">
        <Button type="submit" label="Activar" :loading="busy" />
        <Button label="Cancelar" severity="secondary" text :disabled="busy" @click="reset" />
      </div>
    </form>

    <!-- Pedir contraseña para renovar códigos o desactivar -->
    <form v-else-if="mode === 'renew' || mode === 'disable'" class="max-w-sm space-y-4" novalidate @submit.prevent="mode === 'renew' ? renewCodes() : disable()">
      <p class="text-sm text-app-muted">
        {{ mode === 'renew' ? 'Se generan códigos nuevos y los anteriores dejan de servir.' : 'Se va a desactivar la protección extra de tu cuenta.' }}
      </p>
      <FormField label="Tu contraseña" for="tf-pass2" :error="firstError('password') ?? firstError('code')">
        <Password v-model="password" input-id="tf-pass2" :feedback="false" toggle-mask autocomplete="current-password" fluid />
      </FormField>
      <div class="flex gap-2">
        <Button type="submit" :label="mode === 'renew' ? 'Generar códigos' : 'Desactivar'" :severity="mode === 'disable' ? 'danger' : undefined" :loading="busy" />
        <Button label="Cancelar" severity="secondary" text :disabled="busy" @click="reset" />
      </div>
    </form>

    <!-- Estado -->
    <div v-else class="space-y-3 text-sm">
      <p v-if="enabled"><strong>Activada.</strong> Al iniciar sesión se te pide un código de tu app.</p>
      <p v-else class="text-app-muted">Desactivada. Recomendado, sobre todo si vas a publicar el sistema en internet.</p>

      <p v-if="firstError('code')" class="text-app-danger" role="alert">{{ firstError('code') }}</p>

      <div class="flex flex-wrap gap-3 text-xs">
        <Button v-if="!enabled" label="Activar verificación en dos pasos" :loading="busy" @click="startSetup" />
        <template v-else>
          <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-accent" @click="mode = 'renew'">Renovar códigos de recuperación</button>
          <button type="button" class="cursor-pointer text-app-muted underline underline-offset-4 hover:text-app-danger" @click="mode = 'disable'">Desactivar</button>
        </template>
      </div>
    </div>
  </SectionBlock>
</template>
