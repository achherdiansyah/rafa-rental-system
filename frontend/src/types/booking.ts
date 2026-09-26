import type { EquipmentModel } from './equipment'
import type { ProjectLocation } from './projectLocation'

export type BookingStatus = 'DRAFT' | 'SUBMITTED' | 'PENDING_APPROVAL' | 'REJECTED' | 'APPROVED' | 'PAYMENT_PENDING' | 'CONFIRMED' | 'DISPATCHED' | 'ARRIVED' | 'ONGOING' | 'COMPLETED' | 'CANCELLED' | 'EXPIRED'

export interface BookingDetail {
  id: number
  booking_id: number
  equipment_model_id: number
  quantity: number
  start_date: string
  end_date: string
  is_all_in: boolean
  rental_rate_snapshot: number
  subtotal: number
  model?: EquipmentModel
  created_at: string
}

export interface Booking {
  id: number
  booking_code: string
  user_id: number
  project_location_id: number
  status: BookingStatus
  rejection_reason: string | null
  total_amount: number
  project_location?: ProjectLocation | null
  details: BookingDetail[]
  created_at: string
  updated_at: string
}