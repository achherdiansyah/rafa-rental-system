import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'

export interface CustomerProfileData {
  company_name: string | null
  identity_type: string | null
  identity_number: string | null
  address: string | null
  verification_status: string | null
}

export interface AdminUserData {
  id: number
  name: string
  email: string
  role: string
  phone_number: string | null
  is_active: boolean
  customer_profile?: CustomerProfileData | null
  created_at: string
}

export const userService = {
  getUsers: async (params?: { role?: string; page?: number; per_page?: number }): Promise<ApiResponse<AdminUserData[]>> => {
    return await api.get<AdminUserData[]>('/admin/users', { params })
  },

  getUser: async (id: number): Promise<ApiResponse<AdminUserData>> => {
    return await api.get<AdminUserData>(`/admin/users/${id}`)
  },

  verifyAccount: async (userId: number, status: 'VERIFIED' | 'REJECTED'): Promise<ApiResponse<{ user_id: number; verification_status: string }>> => {
    return await api.post<{ user_id: number; verification_status: string }>('/profile/verify', {
      user_id: userId,
      verification_status: status,
    })
  },
}
