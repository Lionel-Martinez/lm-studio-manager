<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'

import PaymentFormDialog from '@/components/payments/PaymentFormDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { usePaymentsStore } from '@/stores/payments'
import { paymentStatusLabels, type Payment, type PaymentStatus } from '@/types/payment'
import { formatCurrency, formatShortDate, toISODate } from '@/utils/dates'

const store = usePaymentsStore()

const filters: { value: PaymentStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'Todos' },
  { value: 'pending', label: 'Pendientes' },
  { value: 'overdue', label: 'Vencidos' },
  { value: 'paid', label: 'Cobrados' },
]

onMounted(() => store.fetchPayments(1))
watch(() => store.filter, () => store.fetchPayments(1))

const actionError = ref('')

async function markPaid(payment: Payment) {
  actionError.value = ''
  try {
    await store.markPaid(payment, toISODate(new Date()))
  } catch {
    actionError.value = 'No se pudo marcar el pago como cobrado.'
  }
}

const toDelete = ref<Payment | null>(null)
const deleting = ref(false)

async function confirmDelete() {
  if (!toDelete.value) return
  deleting.value = true
  try {
    await store.deletePayment(toDelete.value.id)
    toDelete.value = null
  } catch {
    actionError.value = 'No se pudo eliminar el pago.'
    toDelete.value = null
  } finally {
    deleting.value = false
  }
}

const statusClass = (status: PaymentStatus) =>
  status === 'overdue' ? 'font-semibold text-app-danger' : status === 'paid' ? 'text-app-muted' : ''
</script>

<template>
  <div class="space-y-6">
    <dl v-if="store.summary" class="grid gap-x-8 gap-y-4 border-b border-app-border pb-6 sm:grid-cols-3">
      <div>
        <dt class="text-xs text-app-muted">Por cobrar</dt>
        <dd class="tabular font-display text-3xl font-medium">{{ formatCurrency(store.summary.pending) }}</dd>
      </div>
      <div>
        <dt class="text-xs text-app-muted">Vencido ({{ store.summary.overdue_count }})</dt>
        <dd class="tabular font-display text-3xl font-medium" :class="store.summary.overdue > 0 ? 'text-app-danger' : ''">{{ formatCurrency(store.summary.overdue) }}</dd>
      </div>
      <div>
        <dt class="text-xs text-app-muted">Cobrado este mes</dt>
        <dd class="tabular font-display text-3xl font-medium">{{ formatCurrency(store.summary.collected_this_month) }}</dd>
      </div>
    </dl>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex gap-4 text-sm" role="group" aria-label="Filtrar pagos">
        <button
          v-for="f in filters"
          :key="f.value"
          type="button"
          class="cursor-pointer border-b-2 px-0.5 pb-1 transition-colors"
          :class="store.filter === f.value ? 'border-app-accent font-semibold' : 'border-transparent text-app-muted hover:text-app-text'"
          :aria-pressed="store.filter === f.value"
          @click="store.filter = f.value"
        >
          {{ f.label }}
        </button>
      </div>

      <Button label="Nuevo pago" @click="store.openDialog()" />
    </div>

    <p v-if="store.loadError || actionError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
      {{ store.loadError || actionError }}
    </p>

    <EmptyState
      v-else-if="!store.loading && store.items.length === 0"
      :title="store.filter === 'all' ? 'Todavía no registraste pagos' : 'No hay pagos en esta categoría'"
      description="Cargá señas, cuotas o abonos con su fecha de vencimiento para no perder de vista lo que te deben."
    />

    <div v-else class="overflow-x-auto">
      <table class="w-full min-w-[820px] border-collapse text-sm">
        <thead>
          <tr class="border-b-2 border-app-rule text-left text-xs text-app-muted">
            <th scope="col" class="py-2 pr-4 font-medium">Cliente</th>
            <th scope="col" class="py-2 pr-4 font-medium">Concepto</th>
            <th scope="col" class="py-2 pr-4 font-medium">Vence</th>
            <th scope="col" class="py-2 pr-4 text-right font-medium">Importe</th>
            <th scope="col" class="py-2 pr-4 font-medium">Estado</th>
            <th scope="col" class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in store.items" :key="payment.id" class="border-b border-app-border align-top">
            <td class="py-3 pr-4 font-semibold">{{ payment.client_name }}</td>
            <td class="py-3 pr-4">
              {{ payment.concept }}
              <p v-if="payment.project_name" class="text-xs text-app-muted">{{ payment.project_name }}</p>
            </td>
            <td class="tabular py-3 pr-4">{{ formatShortDate(payment.due_date) }}</td>
            <td class="tabular py-3 pr-4 text-right">{{ formatCurrency(payment.amount) }}</td>
            <td class="py-3 pr-4" :class="statusClass(payment.status)">
              {{ paymentStatusLabels[payment.status] }}
              <p v-if="payment.paid_at" class="text-xs font-normal text-app-muted">
                {{ formatShortDate(payment.paid_at) }}<template v-if="payment.method"> · {{ payment.method }}</template>
              </p>
            </td>
            <td class="whitespace-nowrap py-3 text-right text-xs">
              <button v-if="payment.status !== 'paid'" type="button" class="mr-3 cursor-pointer underline underline-offset-4 hover:text-app-accent" @click="markPaid(payment)">Marcar cobrado</button>
              <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-accent" @click="store.openDialog(payment)">Editar</button>
              <button type="button" class="ml-3 cursor-pointer text-app-muted underline underline-offset-4 hover:text-app-danger" @click="toDelete = payment">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta && store.meta.current_page < store.meta.last_page" class="pt-4 text-center">
        <Button label="Cargar más" severity="secondary" text :loading="store.loading" @click="store.loadMore()" />
      </div>
    </div>

    <PaymentFormDialog />

    <Dialog :visible="toDelete !== null" modal header="Eliminar pago" :draggable="false" :style="{ width: '28rem', maxWidth: '95vw' }" @update:visible="(v: boolean) => { if (!v) toDelete = null }">
      <p class="text-sm">¿Eliminar el pago <strong>{{ toDelete?.concept }}</strong> de {{ toDelete?.client_name }}? Se oculta de las listas pero se conserva en la base de datos.</p>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text :disabled="deleting" @click="toDelete = null" />
        <Button label="Eliminar" severity="danger" :loading="deleting" @click="confirmDelete" />
      </template>
    </Dialog>
  </div>
</template>
