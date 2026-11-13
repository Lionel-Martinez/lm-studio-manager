<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

import FormField from '@/components/ui/FormField.vue'
import { getValidationErrors, type FieldErrors } from '@/lib/api'
import { usePaymentsStore } from '@/stores/payments'
import type { PaymentPayload } from '@/types/payment'
import { parseISODate, toISODate } from '@/utils/dates'

const store = usePaymentsStore()

function emptyForm() {
  return {
    client_id: null as number | null,
    project_id: null as number | null,
    concept: '',
    amount: null as number | null,
    due_date: null as Date | null,
    paid_at: null as Date | null,
    method: '',
    notes: '',
  }
}

const form = reactive(emptyForm())
const errors = ref<FieldErrors>({})
const generalError = ref('')
const saving = ref(false)

const isEditing = computed(() => store.editing !== null)
const visible = computed({
  get: () => store.dialogOpen,
  set: (value: boolean) => {
    if (!value) store.closeDialog()
  },
})

const clientChoices = computed(() => store.clientOptions.map((c) => ({ value: c.id, label: c.display_name })))
const projectChoices = computed(() => {
  const client = store.clientOptions.find((c) => c.id === form.client_id)
  return (client?.projects ?? []).map((p) => ({ value: p.id, label: p.name || p.service_type_label }))
})

watch(
  () => store.dialogOpen,
  async (open) => {
    if (!open) return
    Object.assign(form, emptyForm())
    errors.value = {}
    generalError.value = ''

    try {
      await store.fetchClientOptions()
    } catch {
      generalError.value = 'No se pudo cargar la lista de clientes.'
    }

    const p = store.editing
    if (p) {
      Object.assign(form, {
        client_id: p.client_id,
        project_id: p.project_id,
        concept: p.concept,
        amount: p.amount,
        due_date: parseISODate(p.due_date),
        paid_at: p.paid_at ? parseISODate(p.paid_at) : null,
        method: p.method ?? '',
        notes: p.notes ?? '',
      })
    }
  },
)

// Si se cambia de cliente, el proyecto elegido ya no corresponde.
watch(() => form.client_id, (_new, old) => {
  if (old !== null && !isEditing.value) form.project_id = null
})

const firstError = (field: string) => errors.value[field]?.[0]

async function submit() {
  errors.value = {}
  generalError.value = ''

  const missing: FieldErrors = {}
  if (!isEditing.value && !form.client_id) missing.client_id = ['Elegí el cliente.']
  if (!form.concept.trim()) missing.concept = ['El concepto es obligatorio.']
  if (!form.amount || form.amount <= 0) missing.amount = ['Ingresá un importe mayor a cero.']
  if (!form.due_date) missing.due_date = ['Indicá la fecha de vencimiento.']
  if (Object.keys(missing).length) {
    errors.value = missing
    return
  }

  const payload: PaymentPayload = {
    project_id: form.project_id,
    concept: form.concept.trim(),
    amount: form.amount!,
    due_date: toISODate(form.due_date!),
    paid_at: form.paid_at ? toISODate(form.paid_at) : null,
    method: form.method.trim() || null,
    notes: form.notes.trim() || null,
  }
  if (!isEditing.value) payload.client_id = form.client_id!

  saving.value = true

  try {
    await store.savePayment(payload)
    store.closeDialog()
  } catch (error) {
    const validation = getValidationErrors(error)
    if (validation) errors.value = validation
    else generalError.value = 'No se pudo guardar el pago. Intentá de nuevo.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="isEditing ? 'Editar pago' : 'Nuevo pago'" :draggable="false" :style="{ width: '38rem', maxWidth: '95vw' }">
    <form id="payment-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="submit">
      <p v-if="generalError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger sm:col-span-2" role="alert">{{ generalError }}</p>

      <FormField label="Cliente" for="pay-client" :error="firstError('client_id')" required>
        <Select v-model="form.client_id" input-id="pay-client" :options="clientChoices" option-label="label" option-value="value" placeholder="Elegí un cliente" filter :disabled="isEditing" fluid :invalid="!!firstError('client_id')" />
      </FormField>

      <FormField label="Proyecto" for="pay-project" hint="Opcional." :error="firstError('project_id')">
        <Select v-model="form.project_id" input-id="pay-project" :options="projectChoices" option-label="label" option-value="value" placeholder="Sin proyecto" show-clear :disabled="!form.client_id" fluid />
      </FormField>

      <div class="sm:col-span-2">
        <FormField label="Concepto" for="pay-concept" hint="Por ejemplo: Seña, cuota 2/3, mantenimiento mensual." :error="firstError('concept')" required>
          <InputText id="pay-concept" v-model="form.concept" fluid :invalid="!!firstError('concept')" />
        </FormField>
      </div>

      <FormField label="Importe (ARS)" for="pay-amount" :error="firstError('amount')" required>
        <InputNumber v-model="form.amount" input-id="pay-amount" mode="currency" currency="ARS" locale="es-AR" :min="0" fluid :invalid="!!firstError('amount')" />
      </FormField>

      <FormField label="Vence" for="pay-due" :error="firstError('due_date')" required>
        <DatePicker v-model="form.due_date" input-id="pay-due" date-format="dd/mm/yy" show-icon fluid :invalid="!!firstError('due_date')" />
      </FormField>

      <FormField label="Fecha de cobro" for="pay-paid" hint="Dejalo vacío si todavía no se cobró." :error="firstError('paid_at')">
        <DatePicker v-model="form.paid_at" input-id="pay-paid" date-format="dd/mm/yy" show-icon show-button-bar fluid />
      </FormField>

      <FormField label="Medio de pago" for="pay-method" :error="firstError('method')">
        <InputText id="pay-method" v-model="form.method" placeholder="Transferencia, efectivo…" fluid />
      </FormField>

      <div class="sm:col-span-2">
        <FormField label="Notas" for="pay-notes" :error="firstError('notes')">
          <InputText id="pay-notes" v-model="form.notes" fluid />
        </FormField>
      </div>
    </form>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="saving" @click="store.closeDialog()" />
      <Button type="submit" form="payment-form" :label="isEditing ? 'Guardar cambios' : 'Guardar pago'" :loading="saving" />
    </template>
  </Dialog>
</template>
