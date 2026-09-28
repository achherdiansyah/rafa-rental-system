import { api } from '@/lib/api'
import apiClient from '@/lib/api'
import type { DashboardReport } from '@/types/reporting'

export interface ReportMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ReportPage {
  data: Record<string, unknown>[]
  meta: ReportMeta
}

export interface ReportQuery {
  from?: string
  to?: string
  status?: string
  source?: string
  customer_id?: string
  project_id?: string
  model_id?: string
  unit_id?: string
  rental_id?: string
  invoice_id?: string
  per_page?: number
  page?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
}

const AS_LIST = {
  bookings: { path: '/reports/operational/bookings' },
  timesheets: { path: '/reports/operational/timesheets' },
  rentals: { path: '/reports/operational/rentals' },
  activity: { path: '/reports/operational/activity' },
  equipment: { path: '/reports/operational/equipment' },
  invoices: { path: '/reports/financial/invoices' },
  payments: { path: '/reports/financial/payments' },
  partials: { path: '/reports/financial/partials' },
  outstanding: { path: '/reports/financial/outstanding' },
  overpayments: { path: '/reports/financial/overpayments' },
  refunds: { path: '/reports/financial/refunds' },
} as const

export type ReportType = keyof typeof AS_LIST

export const reportingService = {
  getDashboard: async (params?: { from?: string; to?: string }): Promise<DashboardReport> => {
    const response = await api.get<DashboardReport>('/reports/dashboard', { params })
    return response.data
  },

  listReport: async (type: ReportType, params: ReportQuery = {}): Promise<ReportPage> => {
    const { path } = AS_LIST[type]
    const cleaned = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== ''))
    const response = await api.get<Record<string, unknown>[]>(path, { params: cleaned })
    return {
      data: response.data ?? [],
      meta: response.meta ?? { current_page: 1, per_page: 20, total: 0, last_page: 1 },
    }
  },

  async downloadCsv(type: ReportType, params: ReportQuery = {}): Promise<string> {
    const cleaned = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== ''))
    const response = await apiClient.get(`/reports/export/${type}`, { params: cleaned, responseType: 'blob' })
    return URL.createObjectURL(response.data)
  },
}