import type { Booking } from './booking'

export type RentalStatus =
  | 'ASSIGNED'
  | 'DISPATCHED'
  | 'ARRIVED'
  | 'ONGOING'
  | 'DEMOBILIZING'
  | 'RETURN_INSPECTED'
  | 'COMPLETED'
  | 'CANCELLED'

export interface RentalDetail {
  id: number
  rental_id: number
  assignment_id: number
  status: string
  check_in_hm: number | null
  check_out_hm: number | null
  condition_notes: string | null
  unit?: {
    id: number
    serial_number: string
    plate_number: string | null
    status: string
  }
}

export interface Rental {
  id: number
  booking_id: number
  status: RentalStatus
  started_at: string | null
  completed_at: string | null
  booking?: Booking
  details: RentalDetail[]
  created_at: string
  updated_at: string
}