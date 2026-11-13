import { ref } from 'vue'
import { defineStore } from 'pinia'

import { api } from '@/lib/api'
import type { Client, PaginationMeta } from '@/types/client'
import type { Payment, PaymentPayload, PaymentStatus, PaymentSummary } from '@/types/payment'

export const usePaymentsStore = defineStore('payments', () => {
  const items = ref<Payment[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const summary = ref<PaymentSummary | null>(null)
  const filter = ref<PaymentStatus | 'all'>('all')
  const loading = ref(false)
  const loadError = ref<string | null>(null)

  /** Clientes para elegir en el formulario (los primeros 100). */
  const clientOptions = ref<Client[]>([])

  const dialogOpen = ref(false)
  const editing = ref<Payment | null>(null)

  async function fetchPayments(page = 1) {
    loading.value = true
    loadError.value = null

    try {
      const [list, totals] = await Promise.all([
        api.get('/payments', { params: { page, status: filter.value === 'all' ? undefined : filter.value } }),
        api.get('/payments/summary'),
      ])

      items.value = page === 1 ? list.data.data : [...items.value, ...list.data.data]
      meta.value = list.data.meta
      summary.value = totals.data.data
    } catch {
      loadError.value = 'No se pudieron cargar los pagos. Verificá que el backend esté en marcha.'
    } finally {
      loading.value = false
    }
  }

  async function loadMore() {
    if (meta.value && meta.value.current_page < meta.value.last_page) await fetchPayments(meta.value.current_page + 1)
  }

  async function fetchClientOptions() {
    const { data } = await api.get('/clients', { params: { per_page: 100 } })
    clientOptions.value = data.data
  }

  async function savePayment(payload: PaymentPayload) {
    if (editing.value) await api.put(`/payments/${editing.value.id}`, payload)
    else await api.post('/payments', payload)
    await fetchPayments(1)
  }

  /** Marca como cobrado hoy, conservando el resto de los datos. */
  async function markPaid(payment: Payment, paidAt: string) {
    await api.put(`/payments/${payment.id}`, {
      project_id: payment.project_id,
      concept: payment.concept,
      amount: payment.amount,
      due_date: payment.due_date,
      paid_at: paidAt,
      method: payment.method,
      notes: payment.notes,
    })
    await fetchPayments(1)
  }

  async function deletePayment(id: number) {
    await api.delete(`/payments/${id}`)
    await fetchPayments(1)
  }

  function openDialog(payment: Payment | null = null) {
    editing.value = payment
    dialogOpen.value = true
  }

  function closeDialog() {
    dialogOpen.value = false
  }

  return {
    items, meta, summary, filter, loading, loadError, clientOptions, dialogOpen, editing,
    fetchPayments, loadMore, fetchClientOptions, savePayment, markPaid, deletePayment, openDialog, closeDialog,
  }
})
