import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'

export interface OperatorOption {
  id: number
  name: string
  phone_number: string | null
  email: string | null
}

export interface RentalOperatorAssignment {
  id: number
  rental_id: number
  rental_detail_id: number
  operator_user_id: number
  operator_name: string | null
  operator_phone?: string | null
  unit_serial: string | null
  assigned_by_name?: string | null
  started_at: string | null
  ended_at: string | null
  status: string
  end_reason: string | null
}

export const operatorAssignmentService = {
  async getOperators(): Promise<ApiResponse<OperatorOption[]>> {
    return api.get<OperatorOption[]>('/admin/operators')
  },

  async getAssignments(rentalId: number): Promise<ApiResponse<RentalOperatorAssignment[]>> {
    return api.get<RentalOperatorAssignment[]>(`/admin/rentals/${rentalId}/operator-assignments`)
  },

  async assign(rentalId: number, rentalDetailId: number, operatorUserId: number): Promise<ApiResponse<RentalOperatorAssignment>> {
    return api.post<RentalOperatorAssignment, { rental_detail_id: number; operator_user_id: number }>(
      `/admin/rentals/${rentalId}/operator-assignments`,
      { rental_detail_id: rentalDetailId, operator_user_id: operatorUserId }
    )
  },

  async replace(assignmentId: number, operatorUserId: number, reason: string): Promise<ApiResponse<RentalOperatorAssignment>> {
    return api.post<RentalOperatorAssignment, { operator_user_id: number; reason: string }>(
      `/admin/operator-assignments/${assignmentId}/replace`,
      { operator_user_id: operatorUserId, reason }
    )
  },

  async end(assignmentId: number, reason: string): Promise<ApiResponse<RentalOperatorAssignment>> {
    return api.post<RentalOperatorAssignment, { reason: string }>(
      `/admin/operator-assignments/${assignmentId}/end`,
      { reason }
    )
  },
}
