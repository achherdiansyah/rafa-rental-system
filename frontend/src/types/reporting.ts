export interface DashboardReport {
  period: { from: string | null; to: string | null }
  bookings: { total: number; by_status: Record<string, number> }
  rentals: {
    total: number
    active: number
    by_status: Record<string, number>
    by_project: { project_id: number; project_name: string; total: number }[]
  }
  timesheet: { total_hours: number; by_month: { month: string; total_hours: number }[] }
  equipment: {
    fleet_total: number
    available: number
    in_use: number
    maintenance: number
    utilization_hours: number
    top_models: { model_id: number; model: string; total_hours: number; unit_count: number; rental_lines: number }[]
  }
  financial: {
    invoices: { status: string; count: number; grand_total: number; paid_total: number; balance: number }[]
    payments: { approved_count: number; approved_amount: number }
    refunds: { status: string; count: number; amount: number }[]
  }
  outstanding: { customer_count: number; total: number }
}