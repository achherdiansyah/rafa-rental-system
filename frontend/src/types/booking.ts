import type { EquipmentModel } from './equipment'
import type { ProjectLocation } from './projectLocation'

export type BookingStatus = 'DRAFT' | 'PENDING_APPROVAL' | 'REJECTED' | 'APPROVED' | 'PAYMENT_PENDING' | 'CONFIRMED' | 'DISPATCHED' | 'ARRIVED' | 'ONGOING' | 'COMPLETED' | 'CANCELLED' | 'EXPIRED'

export interface BookingUnitAssignmentSummary {
  id: number
  equipment_unit_id: number
  status: string
  is_current: boolean
  replaced_reason: string | null
  unit?: {
    id: number
    serial_number: string
    plate_number: string | null
    status: string
  }
}

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
  unit_assignments?: BookingUnitAssignmentSummary[]
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
  approved_at?: string | null
  payment_deadline_at?: string | null
  payment_met_at?: string | null
  cancelled_at?: string | null
  cancellation_reason?: string | null
  reschedule_requested_at?: string | null
  reschedule_reason?: string | null
  reschedule_history?: Record<string, any>[] | null
  project_location?: ProjectLocation | null
  details: BookingDetail[]
  created_at: string
  updated_at: string
}