<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'

import { api } from '@/lib/api'
import type { Receipt } from '@/types/payment'
import { formatCurrency } from '@/utils/dates'

const route = useRoute()
const router = useRouter()

const receipt = ref<Receipt | null>(null)
const error = ref('')
const voiding = ref(false)
const confirmingVoid = ref(false)

onMounted(async () => {
  try {
    const { data } = await api.get(`/receipts/${route.params.id}`)
    receipt.value = data.data
    document.title = `Comprobante ${data.data.code}`
  } catch {
    error.value = 'No se encontró el comprobante.'
  }
})

const longDate = (iso: string) =>
  new Intl.DateTimeFormat('es-AR', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(iso.length === 10 ? `${iso}T00:00:00Z` : iso))

const issuedOn = computed(() => (receipt.value ? new Intl.DateTimeFormat('es-AR', { dateStyle: 'long' }).format(new Date(receipt.value.issued_at)) : ''))

const print = () => window.print()

async function voidReceipt() {
  if (!receipt.value) return
  voiding.value = true
  try {
    const { data } = await api.post(`/receipts/${receipt.value.id}/void`)
    receipt.value = data.data
    confirmingVoid.value = false
  } catch {
    error.value = 'No se pudo anular el comprobante.'
  } finally {
    voiding.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-app-bg px-5 py-8 text-app-text print:bg-white print:p-0">
    <div class="mx-auto mb-6 flex max-w-2xl flex-wrap items-center justify-between gap-3 print:hidden">
      <button type="button" class="cursor-pointer text-sm underline underline-offset-4 hover:text-app-accent" @click="router.push({ name: 'pagos' })">← Volver a pagos</button>

      <div v-if="receipt" class="flex items-center gap-2">
        <template v-if="!receipt.voided_at">
          <Button v-if="!confirmingVoid" label="Anular" severity="secondary" text size="small" @click="confirmingVoid = true" />
          <template v-else>
            <span class="text-xs text-app-muted">¿Anular este comprobante?</span>
            <Button label="Sí, anular" severity="danger" size="small" :loading="voiding" @click="voidReceipt" />
            <Button label="No" severity="secondary" text size="small" :disabled="voiding" @click="confirmingVoid = false" />
          </template>
        </template>
        <Button label="Imprimir o guardar PDF" size="small" @click="print" />
      </div>
    </div>

    <p v-if="error" class="mx-auto max-w-2xl border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ error }}</p>

    <article v-if="receipt" class="relative mx-auto max-w-2xl border border-app-border bg-app-surface p-8 sm:p-12 print:max-w-none print:border-0 print:bg-white print:p-0">
      <p
        v-if="receipt.voided_at"
        class="pointer-events-none absolute inset-0 flex items-center justify-center font-display text-8xl font-semibold uppercase tracking-widest text-app-danger/20 [transform:rotate(-20deg)]"
        aria-hidden="true"
      >
        Anulado
      </p>

      <header class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-app-rule pb-5">
        <div>
          <p class="font-display text-2xl font-semibold">{{ receipt.business.name }}</p>
          <p v-if="receipt.business.tax_id" class="text-xs text-app-muted">CUIT {{ receipt.business.tax_id }}</p>
          <p v-if="receipt.business.address" class="text-xs text-app-muted">{{ receipt.business.address }}</p>
          <p v-if="receipt.business.contact" class="text-xs text-app-muted">{{ receipt.business.contact }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs uppercase tracking-wide text-app-muted">Comprobante de pago</p>
          <p class="tabular font-display text-2xl font-medium">{{ receipt.code }}</p>
          <p class="text-xs text-app-muted">Emitido el {{ issuedOn }}</p>
        </div>
      </header>

      <dl class="mt-6 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
        <div>
          <dt class="text-xs text-app-muted">Recibimos de</dt>
          <dd class="font-semibold">{{ receipt.client_name }}</dd>
          <dd v-if="receipt.client_tax_id" class="text-xs text-app-muted">CUIT/DNI {{ receipt.client_tax_id }}</dd>
        </div>
        <div>
          <dt class="text-xs text-app-muted">Fecha de pago</dt>
          <dd>{{ longDate(receipt.paid_at) }}</dd>
          <dd v-if="receipt.method" class="text-xs text-app-muted">Medio: {{ receipt.method }}</dd>
        </div>
        <div class="sm:col-span-2">
          <dt class="text-xs text-app-muted">Concepto</dt>
          <dd>{{ receipt.concept }}</dd>
          <dd v-if="receipt.project_name" class="text-xs text-app-muted">Proyecto: {{ receipt.project_name }}</dd>
        </div>
      </dl>

      <div class="mt-8 flex items-baseline justify-between border-y-2 border-app-rule py-4">
        <span class="text-sm">Importe recibido</span>
        <span class="tabular font-display text-4xl font-medium">{{ formatCurrency(receipt.amount) }}</span>
      </div>

      <p class="mt-8 text-xs text-app-muted">
        Comprobante interno de pago. No es una factura ni tiene validez fiscal.
      </p>
      <p v-if="receipt.voided_at" class="mt-1 text-xs font-semibold text-app-danger">Comprobante anulado el {{ longDate(receipt.voided_at) }}.</p>
    </article>
  </main>
</template>
