import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { Booking } from '@/types/booking'

export const supervisorService = {
  async getQueue(params?: { queue?: 'pending' | 'revision' | 'history'; page?: number; per_page?: number }): Promise<ApiResponse<Booking[]>> {
    return api.get<Booking[]>('/supervisor/bookings', { params })
  },

  async getBooking(id: number): Promise<ApiResponse<Booking>> {
    return api.get<Booking>(`/supervisor/bookings/${id}`)
  },

  async validate(id: number, notes?: string): Promise<ApiResponse<Booking>> {
    return api.post<Booking, { notes?: string }>(`/supervisor/bookings/${id}/validate`, { notes })
  },

  async returnForRevision(id: number, notes: string): Promise<ApiResponse<Booking>> {
    return api.post<Booking, { notes: string }>(`/supervisor/bookings/${id}/return`, { notes })
  },
}
