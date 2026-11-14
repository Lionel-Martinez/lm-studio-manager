export type PaymentStatus = 'pending' | 'overdue' | 'paid'

export const paymentStatusLabels: Record<PaymentStatus, string> = {
  pending: 'Pendiente',
  overdue: 'Vencido',
  paid: 'Cobrado',
}

export interface Payment {
  id: number
  client_id: number
  client_name: string | null
  project_id: number | null
  project_name: string | null
  concept: string
  amount: number
  due_date: string
  paid_at: string | null
  status: PaymentStatus
  method: string | null
  notes: string | null
  receipt_id?: number | null
  receipt_code?: string | null
  receipt_voided?: boolean | null
}

/** Comprobante interno de pago (sin validez fiscal). */
export interface Receipt {
  id: number
  code: string
  client_name: string
  client_tax_id: string | null
  concept: string
  project_name: string | null
  amount: number
  paid_at: string
  method: string | null
  issued_at: string
  voided_at: string | null
  business: { name: string; tax_id: string | null; address: string | null; contact: string | null }
}

/** Datos que se envían al crear (con `client_id`) o editar un pago. */
export interface PaymentPayload {
  client_id?: number
  project_id: number | null
  concept: string
  amount: number
  due_date: string
  paid_at: string | null
  method: string | null
  notes: string | null
}

export interface PaymentSummary {
  pending: number
  overdue: number
  overdue_count: number
  collected_this_month: number
}
