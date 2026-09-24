import { api } from '@/lib/api'
import type {
  AuthResponseData,
  AuthUser,
  LoginCredentials,
  RegisterData,
  ResetPasswordPayload,
} from '@/types/auth'

export const authService = {
  /**
   * Send login credentials to backend Sanctum token issuer.
   */
  login: async (credentials: LoginCredentials): Promise<AuthResponseData> => {
    const response = await api.post<AuthResponseData>('/auth/login', credentials)
    return response.data
  },

  /**
   * Register a new user account.
   */
  register: async (data: RegisterData): Promise<AuthResponseData> => {
    const response = await api.post<AuthResponseData>('/auth/register', data)
    return response.data
  },

  /**
   * Request password reset link.
   */
  forgotPassword: async (email: string): Promise<string> => {
    const response = await api.post<null>('/auth/forgot-password', { email })
    return response.message
  },

  /**
   * Reset user password using token.
   */
  resetPassword: async (payload: ResetPasswordPayload): Promise<string> => {
    const response = await api.post<null>('/auth/reset-password', payload)
    return response.message
  },

  /**
   * Revoke current Sanctum token on backend.
   */
  logout: async (): Promise<void> => {
    await api.post('/auth/logout')
  },

  /**
   * Fetch current authenticated user details.
   */
  getMe: async (): Promise<AuthUser> => {
    const response = await api.get<AuthUser>('/auth/me')
    return response.data
  },
}

export default authService
