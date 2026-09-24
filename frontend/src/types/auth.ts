export type UserRole = 'USER' | 'ADMIN' | 'OWNER'

export type AuthStatus = 'checking' | 'authenticated' | 'unauthenticated'

export interface CustomerProfile {
  company_name: string | null
  identity_type: 'KTP' | 'NPWP' | 'PASSPORT'
  identity_number: string | null
  address: string | null
  verification_status: 'UNVERIFIED' | 'VERIFIED' | 'REJECTED'
}

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  phone_number: string | null
  is_active: boolean
  customer_profile?: CustomerProfile | null
  created_at?: string
}

export interface AuthResponseData {
  user: AuthUser
  token: string
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
  phone_number: string
  company_name?: string
  identity_type?: 'KTP' | 'NPWP' | 'PASSPORT'
  identity_number?: string
  address?: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface AuthContextType {
  user: AuthUser | null
  token: string | null
  status: AuthStatus
  isAuthenticated: boolean
  isChecking: boolean
  login: (credentials: LoginCredentials) => Promise<AuthUser>
  register: (data: RegisterData) => Promise<AuthUser>
  logout: () => Promise<void>
  refreshUser: () => Promise<AuthUser | null>
}
