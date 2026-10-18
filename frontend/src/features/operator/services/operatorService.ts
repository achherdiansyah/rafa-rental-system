import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'

export interface OperatorProjectLocation {
  id: number
  project_name: string
  address: string
  city: string
  latitude: number | null
  longitude: number | null
  pic_name: string | null
  pic_phone: string | null
}

export interface OperatorAssignment {
  id: number
  rental_id: number
  rental_detail_id: number
  rental_status: string | null
  booking_code: string | null
  project_location: OperatorProjectLocation | null
  unit: { id: number; serial_number: string; plate_number: string | null; status: string } | null
  status: string
  started_at: string | null
  ended_at: string | null
  payment_hold?: {
    id: number
    reason_code: string
    outstanding_amount: number
    started_at: string | null
  } | null
}

export interface OperatorDashboard {
  active_assignments: number
  active_rentals: number
  assignments: OperatorAssignment[]
}

export const operatorService = {
  async getDashboard(): Promise<ApiResponse<OperatorDashboard>> {
    return api.get<OperatorDashboard>('/operator/dashboard')
  },

  async getAssignments(): Promise<ApiResponse<OperatorAssignment[]>> {
    return api.get<OperatorAssignment[]>('/operator/assignments')
  },

  async getAssignment(id: number): Promise<ApiResponse<OperatorAssignment>> {
    return api.get<OperatorAssignment>(`/operator/assignments/${id}`)
  },
}
