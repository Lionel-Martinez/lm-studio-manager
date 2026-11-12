<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import Button from 'primevue/button'
import Password from 'primevue/password'

import FormField from '@/components/ui/FormField.vue'
import SectionBlock from '@/components/ui/SectionBlock.vue'
import { api, getValidationErrors, type FieldErrors } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { ActivityEntry } from '@/types/auth'

const auth = useAuthStore()

const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const errors = ref<FieldErrors>({})
const generalError = ref('')
const done = ref(false)
const saving = ref(false)

const activity = ref<ActivityEntry[]>([])
const activityError = ref(false)

const firstError = (field: string) => errors.value[field]?.[0]

async function loadActivity() {
  try {
    const { data } = await api.get('/account/activity')
    activity.value = data.data
  } catch {
    activityError.value = true
  }
}

async function submit() {
  errors.value = {}
  generalError.value = ''
  done.value = false
  saving.value = true

  try {
    await api.put('/account/password', form)
    Object.assign(form, { current_password: '', password: '', password_confirmation: '' })
    done.value = true
    await loadActivity()
  } catch (e) {
    const validation = getValidationErrors(e)
    if (validation) errors.value = validation
    else generalError.value = 'No se pudo cambiar la contraseña. Intentá de nuevo.'
  } finally {
    saving.value = false
  }
}

const formatWhen = (iso: string) =>
  new Intl.DateTimeFormat('es-AR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))

onMounted(loadActivity)
</script>

<template>
  <div class="grid gap-12 lg:grid-cols-2">
    <SectionBlock title="Contraseña" :description="`Sesión iniciada como ${auth.user?.email}.`">
      <form class="max-w-sm space-y-4" novalidate @submit.prevent="submit">
        <p v-if="generalError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
          {{ generalError }}
        </p>
        <p v-if="done" class="border-l-2 border-app-accent bg-app-accent-soft px-3 py-2 text-sm" role="status">
          Contraseña actualizada. Se cerraron tus otras sesiones abiertas.
        </p>

        <FormField label="Contraseña actual" for="acc-current" :error="firstError('current_password')">
          <Password v-model="form.current_password" input-id="acc-current" :feedback="false" toggle-mask autocomplete="current-password" fluid />
        </FormField>

        <FormField
          label="Contraseña nueva"
          for="acc-new"
          hint="Mínimo 12 caracteres, con mayúsculas, minúsculas, números y símbolos."
          :error="firstError('password')"
        >
          <Password v-model="form.password" input-id="acc-new" :feedback="false" toggle-mask autocomplete="new-password" fluid />
        </FormField>

        <FormField label="Repetir contraseña nueva" for="acc-confirm">
          <Password v-model="form.password_confirmation" input-id="acc-confirm" :feedback="false" toggle-mask autocomplete="new-password" fluid />
        </FormField>

        <Button type="submit" label="Cambiar contraseña" :loading="saving" />
      </form>
    </SectionBlock>

    <SectionBlock title="Actividad reciente" description="Accesos, intentos fallidos y cambios en tus datos.">
      <p v-if="activityError" class="text-sm text-app-danger" role="alert">No se pudo cargar la actividad.</p>
      <p v-else-if="activity.length === 0" class="text-sm text-app-muted">Sin actividad registrada todavía.</p>

      <ul v-else class="divide-y divide-app-border text-sm">
        <li v-for="entry in activity" :key="entry.id" class="flex items-baseline justify-between gap-4 py-2">
          <span :class="entry.event === 'auth.failed' || entry.event === 'auth.lockout' ? 'text-app-danger' : ''">{{ entry.label }}</span>
          <span class="tabular shrink-0 text-xs text-app-muted">
            {{ formatWhen(entry.created_at) }}<template v-if="entry.ip_address"> · {{ entry.ip_address }}</template>
          </span>
        </li>
      </ul>
    </SectionBlock>
  </div>
</template>
