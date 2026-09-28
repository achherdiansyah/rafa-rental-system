import { api } from '@/lib/api'
import type { DashboardReport } from '@/types/reporting'

export const reportingService = {
  getDashboard: async (params?: { from?: string; to?: string }): Promise<DashboardReport> => {
    const response = await api.get<DashboardReport>('/reports/dashboard', { params })
    return response.data
  },
}