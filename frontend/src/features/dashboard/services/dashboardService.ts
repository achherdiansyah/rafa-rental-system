import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { Booking } from '@/types/booking'
import type { Rental } from '@/types/rental'
import type { Invoice } from '@/types/invoice'
import type { ProjectLocation } from '@/types/projectLocation'
import type { InAppNotification } from '@/types/notification'

export interface UserDashboardSummary {
  bookings: Booking[]
  rentals: Rental[]
  invoices: Invoice[]
  project_locations: ProjectLocation[]
  notifications: InAppNotification[]
}

export const dashboardService = {
  getUserSummary: async (): Promise<ApiResponse<UserDashboardSummary>> => {
    return await api.get<UserDashboardSummary>('/dashboard/summary')
  },
}
