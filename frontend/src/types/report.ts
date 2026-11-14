export interface LabeledValue {
  label: string
  value: number
}

export interface MonthValue {
  /** Formato AAAA-MM. */
  month: string
  value: number
}

export interface LateProject {
  id: number
  client: string
  project: string
  status: string
  due_date: string
  days_late: number
}

export interface Report {
  period: { from: string; to: string }
  totals: {
    collected: number
    pending: number
    overdue: number
    projects: number
    avg_wait_days: number | null
  }
  revenue_by_month: MonthValue[]
  revenue_by_service: LabeledValue[]
  top_clients: LabeledValue[]
  projects_by_status: LabeledValue[]
  projects_by_service: LabeledValue[]
  new_clients_by_month: MonthValue[]
  late_projects: LateProject[]
}
