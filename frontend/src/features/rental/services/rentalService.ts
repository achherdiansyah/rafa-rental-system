import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { Rental } from '@/types/rental'

export const rentalService = {
  async getRentals(params?: { status?: string; booking_id?: number; page?: number; per_page?: number }): Promise<ApiResponse<Rental[]>> {
    const response = await api.get<Rental[]>('/rentals', { params })
    return response
  },

  async getRental(id: number): Promise<ApiResponse<Rental>> {
    const response = await api.get<Rental>(`/rentals/${id}`)
    return response
  },

  async createFromBooking(bookingId: number): Promise<ApiResponse<Rental>> {
    const response = await api.post<Rental, { booking_id: number }>('/rentals', { booking_id: bookingId })
    return response
  },

  async transition(id: number, target: 'dispatch' | 'arrive' | 'start'): Promise<ApiResponse<Rental>> {
    const response = await api.post<Rental>(`/rentals/${id}/${target}`, {})
    return response
  },
}