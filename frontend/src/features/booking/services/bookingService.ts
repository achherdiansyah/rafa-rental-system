import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { Booking, BookingStatus } from '@/types/booking'

export const bookingService = {
  async createFromCart(): Promise<ApiResponse<Booking>> {
    const response = await api.post<Booking>('/bookings', {})
    return response
  },

  async getBookings(params?: {
    status?: BookingStatus
    page?: number
    per_page?: number
  }): Promise<ApiResponse<Booking[]>> {
    const response = await api.get<Booking[]>('/bookings', { params })
    return response
  },

  async getBooking(id: number): Promise<ApiResponse<Booking>> {
    const response = await api.get<Booking>(`/bookings/${id}`)
    return response
  },

  async submitBooking(id: number): Promise<ApiResponse<Booking>> {
    const response = await api.post<Booking>(`/bookings/${id}/submit`, {})
    return response
  },
}