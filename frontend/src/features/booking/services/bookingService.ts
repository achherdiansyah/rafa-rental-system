import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { Booking, BookingStatus } from '@/types/booking'

export const bookingService = {
  async createFromCart(selectedItemIds?: number[]): Promise<ApiResponse<Booking>> {
    const response = await api.post<Booking>('/bookings', { selected_item_ids: selectedItemIds })
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

  async approveBooking(id: number): Promise<ApiResponse<Booking>> {
    const response = await api.post<Booking>(`/bookings/${id}/approve`, {})
    return response
  },

  async rejectBooking(id: number, reason: string): Promise<ApiResponse<Booking>> {
    const response = await api.post<Booking, { rejection_reason: string }>(`/bookings/${id}/reject`, {
      rejection_reason: reason,
    })
    return response
  },

  async assignUnits(
    id: number,
    assignments: { booking_detail_id: number; equipment_unit_id: number }[]
  ): Promise<ApiResponse<Booking>> {
    const response = await api.post<Booking, { assignments: typeof assignments }>(`/bookings/${id}/assign-units`, {
      assignments,
    })
    return response
  },

  async cancelBooking(id: number, reason: string): Promise<ApiResponse<Booking>> {
    const response = await api.post<Booking, { reason: string }>(`/bookings/${id}/cancel`, { reason })
    return response
  },

  async rescheduleBooking(
    id: number,
    payload: { new_start_date: string; new_end_date: string; reason: string }
  ): Promise<ApiResponse<Booking>> {
    const response = await api.post<Booking, typeof payload>(`/bookings/${id}/reschedule`, payload)
    return response
  },

  async replaceUnit(
    id: number,
    assignmentId: number,
    new_equipment_unit_id: number,
    reason: string
  ): Promise<ApiResponse<Booking>> {
    const response = await api.post<
      Booking,
      { new_equipment_unit_id: number; reason: string }
    >(`/bookings/${id}/assignments/${assignmentId}/replace`, { new_equipment_unit_id, reason })
    return response
  },
}