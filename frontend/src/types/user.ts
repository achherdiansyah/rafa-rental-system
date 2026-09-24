import type { UserRole } from './auth'

export interface CustomerProfile {
  company_name: string | null
  identity_type: 'KTP' | 'NPWP' | 'PASSPORT'
  identity_number: string | null
  address: string | null
  verification_status: 'UNVERIFIED' | 'VERIFIED' | 'REJECTED'
}

export interface UserProfile {
  id: number
  name: string
  email: string
  role: UserRole
  phone_number: string | null
  is_active: boolean
  email_verified_at: string | null
  customer_profile?: CustomerProfile | null
  created_at: string
}

export interface UpdateProfilePayload {
  name?: string
  phone_number?: string
  company_name?: string
  identity_type?: 'KTP' | 'NPWP' | 'PASSPORT'
  identity_number?: string
  address?: string
}
