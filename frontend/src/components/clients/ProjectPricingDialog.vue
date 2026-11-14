<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

import FormField from '@/components/ui/FormField.vue'
import { getValidationErrors, type FieldErrors } from '@/lib/api'
import { useClientsStore } from '@/stores/clients'
import type { PaymentMode, PricingPayload, PricingSchedule, TrialUnit } from '@/types/client'
import { formatCurrency, formatShortDate } from '@/utils/dates'

const store = useClientsStore()

const modeOptions: { value: PaymentMode; label: string }[] = [
  { value: 'single', label: 'Pago único' },
  { value: 'split', label: 'Anticipo y saldo (ej. 50/50)' },
  { value: 'installments', label: 'En cuotas' },
]
const unitOptions: { value: TrialUnit; label: string }[] = [
  { value: 'days', label: 'días' },
  { value: 'weeks', label: 'semanas' },
  { value: 'months', label: 'meses' },
]

const form = reactive({
  total_price: null as number | null,
  payment_mode: 'single' as PaymentMode | null,
  deposit_percent: 50 as number | null,
  installments_count: 3 as number | null,
  plan_start_date: '',
  monthly_fee: null as number | null,
  monthly_months: 12 as number | null,
  free_months: 0 as number | null,
  trial_value: 0 as number | null,
  trial_unit: 'days' as TrialUnit | null,
})

const errors = ref<FieldErrors>({})
const generalError = ref('')
const busy = ref<'preview' | 'save' | 'generate' | null>(null)
const result = ref<PricingSchedule | null>(null)
const notice = ref('')

const dialog = computed(() => store.pricingDialog)
const visible = computed({
  get: () => !!dialog.value,
  set: (value: boolean) => {
    if (!value) store.closePricingDialog()
  },
})
const firstError = (field: string) => errors.value[field]?.[0]

watch(dialog, (d) => {
  if (!d) return
  const p = d.project
  Object.assign(form, {
    total_price: p.total_price,
    payment_mode: p.payment_mode ?? 'single',
    deposit_percent: p.deposit_percent ?? 50,
    installments_count: p.installments_count ?? 3,
    plan_start_date: p.plan_start_date ?? p.estimated_start_date ?? new Date().toLocaleDateString('en-CA'),
    monthly_fee: p.monthly_fee,
    monthly_months: p.monthly_months ?? 12,
    free_months: p.free_months,
    trial_value: p.trial_value,
    trial_unit: p.trial_unit ?? 'days',
  })
  errors.value = {}
  generalError.value = ''
  notice.value = ''
  result.value = null
  if (p.plan_start_date) void run('preview')
})

function payload(): PricingPayload {
  const hasPrice = (form.total_price ?? 0) > 0
  const hasFee = (form.monthly_fee ?? 0) > 0
  return {
    total_price: hasPrice ? form.total_price : null,
    payment_mode: hasPrice ? form.payment_mode : null,
    deposit_percent: hasPrice && form.payment_mode === 'split' ? form.deposit_percent : null,
    installments_count: hasPrice && form.payment_mode === 'installments' ? form.installments_count : null,
    plan_start_date: form.plan_start_date,
    monthly_fee: hasFee ? form.monthly_fee : null,
    monthly_months: hasFee ? form.monthly_months : null,
    free_months: form.free_months ?? 0,
    trial_value: form.trial_value ?? 0,
    trial_unit: (form.trial_value ?? 0) > 0 ? form.trial_unit : null,
  }
}

async function run(action: 'preview' | 'save' | 'generate') {
  const d = dialog.value
  if (!d) return
  errors.value = {}
  generalError.value = ''
  notice.value = ''

  if (!form.plan_start_date) {
    errors.value = { plan_start_date: ['Elegí desde cuándo corre el plan.'] }
    return
  }

  busy.value = action
  try {
    if (action === 'preview') {
      result.value = await store.previewPricing(d.project.id, payload())
    } else {
      result.value = await store.savePricing(d.project.id, payload(), action === 'generate')
      notice.value = action === 'generate' ? `Plan guardado y ${result.value.generated} pagos creados en la sección Pagos.` : 'Plan guardado.'
    }
  } catch (error) {
    const validation = getValidationErrors(error)
    if (validation) errors.value = validation
    else generalError.value = 'No se pudo completar la operación. Intentá de nuevo.'
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Precio y plan de pago" :draggable="false" :style="{ width: '44rem', maxWidth: '95vw' }">
    <p v-if="dialog" class="mb-4 text-sm text-app-muted">{{ dialog.clientName }} · {{ dialog.project.name || dialog.project.service_type_label }}</p>

    <form id="pricing-form" class="space-y-6" novalidate @submit.prevent="run('preview')">
      <p v-if="generalError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ generalError }}</p>
      <p v-if="errors.generate" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ errors.generate[0] }}</p>

      <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="mb-2 font-display text-lg font-medium">Precio del proyecto</legend>

        <FormField label="Precio total" for="pr-total" :error="firstError('total_price')" hint="En pesos. Vacío = sin precio único.">
          <InputNumber v-model="form.total_price" input-id="pr-total" mode="currency" currency="ARS" locale="es-AR" :min="0" fluid :invalid="!!firstError('total_price')" />
        </FormField>

        <FormField label="Inicio del plan" for="pr-start" :error="firstError('plan_start_date')" hint="Desde acá corren los vencimientos, la prueba y los meses gratis." required>
          <InputText id="pr-start" v-model="form.plan_start_date" type="date" fluid :invalid="!!firstError('plan_start_date')" />
        </FormField>

        <FormField label="Forma de pago" for="pr-mode" :error="firstError('payment_mode')">
          <Select v-model="form.payment_mode" input-id="pr-mode" :options="modeOptions" option-label="label" option-value="value" :disabled="!form.total_price" fluid />
        </FormField>

        <FormField v-if="form.total_price && form.payment_mode === 'split'" label="Anticipo (%)" for="pr-deposit" :error="firstError('deposit_percent')" :hint="`El saldo es ${100 - (form.deposit_percent ?? 0)}% y vence en la entrega del proyecto.`">
          <InputNumber v-model="form.deposit_percent" input-id="pr-deposit" :min="1" :max="99" suffix=" %" fluid :invalid="!!firstError('deposit_percent')" />
        </FormField>

        <FormField v-if="form.total_price && form.payment_mode === 'installments'" label="Cantidad de cuotas" for="pr-count" :error="firstError('installments_count')" hint="Mensuales e iguales.">
          <InputNumber v-model="form.installments_count" input-id="pr-count" :min="2" :max="60" fluid :invalid="!!firstError('installments_count')" />
        </FormField>
      </fieldset>

      <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="mb-2 font-display text-lg font-medium">Cuota mensual (mantenimiento, hosting)</legend>

        <FormField label="Importe mensual" for="pr-fee" :error="firstError('monthly_fee')" hint="Vacío = sin cuota mensual.">
          <InputNumber v-model="form.monthly_fee" input-id="pr-fee" mode="currency" currency="ARS" locale="es-AR" :min="0" fluid :invalid="!!firstError('monthly_fee')" />
        </FormField>

        <FormField label="Cantidad de meses a programar" for="pr-months" :error="firstError('monthly_months')">
          <InputNumber v-model="form.monthly_months" input-id="pr-months" :min="1" :max="60" :disabled="!form.monthly_fee" fluid :invalid="!!firstError('monthly_months')" />
        </FormField>

        <FormField label="Período de prueba" for="pr-trial" :error="firstError('trial_value') ?? firstError('trial_unit')" hint="No se cobra la cuota mensual durante la prueba.">
          <div class="flex gap-2">
            <InputNumber v-model="form.trial_value" input-id="pr-trial" :min="0" :max="365" :disabled="!form.monthly_fee" class="w-28" />
            <Select v-model="form.trial_unit" :options="unitOptions" option-label="label" option-value="value" :disabled="!form.monthly_fee || !form.trial_value" aria-label="Unidad de la prueba" class="flex-1" />
          </div>
        </FormField>

        <FormField label="Meses gratis" for="pr-free" :error="firstError('free_months')" hint="Se suman después de la prueba.">
          <InputNumber v-model="form.free_months" input-id="pr-free" :min="0" :max="24" :disabled="!form.monthly_fee" fluid />
        </FormField>
      </fieldset>
    </form>

    <section v-if="result" class="mt-6" aria-label="Calendario de cobros">
      <h3 class="font-display text-lg font-medium">Calendario de cobros</h3>
      <p v-if="result.schedule.length === 0" class="mt-2 text-sm text-app-muted">Con estos datos no hay cobros para programar.</p>
      <template v-else>
        <div class="mt-2 max-h-60 overflow-y-auto">
          <table class="w-full border-collapse text-sm">
            <tbody>
              <tr v-for="row in result.schedule" :key="row.item" class="border-b border-app-border">
                <td class="py-1.5 pr-3">{{ row.concept }}</td>
                <td class="tabular py-1.5 pr-3 text-app-muted">{{ formatShortDate(row.due_date) }}</td>
                <td class="tabular py-1.5 text-right font-medium">{{ formatCurrency(row.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="tabular mt-2 text-right text-sm font-semibold">Total programado: {{ formatCurrency(result.schedule_total) }}</p>
      </template>

      <p v-if="result.plan_locked" class="mt-3 text-xs text-app-danger">Ya hay pagos de este plan cobrados: no se puede rehacer el calendario. Los cambios se editan desde Pagos.</p>
      <p v-else-if="result.existing_plan_payments > 0" class="mt-3 text-xs text-app-muted">Ya hay {{ result.existing_plan_payments }} pagos creados por un plan anterior. Al generar, los pendientes se reemplazan (los cargados a mano no se tocan).</p>
    </section>

    <p v-if="notice" class="mt-4 border-l-2 border-app-accent bg-app-accent-soft px-3 py-2 text-sm" role="status">{{ notice }}</p>

    <template #footer>
      <Button label="Cerrar" severity="secondary" text :disabled="busy !== null" @click="store.closePricingDialog()" />
      <Button label="Ver calendario" severity="secondary" :loading="busy === 'preview'" :disabled="busy !== null" type="submit" form="pricing-form" />
      <Button label="Guardar plan" severity="secondary" :loading="busy === 'save'" :disabled="busy !== null" @click="run('save')" />
      <Button label="Guardar y generar pagos" :loading="busy === 'generate'" :disabled="busy !== null || result?.plan_locked" @click="run('generate')" />
    </template>
  </Dialog>
</template>
