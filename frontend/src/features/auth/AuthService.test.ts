import { describe, it, expect, vi, beforeEach } from 'vitest'
import { authService } from './services/authService'
import { api } from '@/lib/api'

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

describe('AuthService Integration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('login sends credentials and returns user and token', async () => {
    const mockResponse = {
      success: true,
      message: 'Login berhasil.',
      data: {
        user: { id: 1, name: 'Budi', email: 'budi@example.com', role: 'USER', is_active: true, phone_number: '0812' },
        token: '1|testtoken',
      },
    }

    vi.mocked(api.post).mockResolvedValue(mockResponse)

    const result = await authService.login({ email: 'budi@example.com', password: 'password123' })

    expect(api.post).toHaveBeenCalledWith('/auth/login', { email: 'budi@example.com', password: 'password123' })
    expect(result.token).toBe('1|testtoken')
    expect(result.user.name).toBe('Budi')
  })

  it('register sends registration data and returns token', async () => {
    const mockResponse = {
      success: true,
      message: 'Pendaftaran berhasil.',
      data: {
        user: { id: 2, name: 'Siti', email: 'siti@example.com', role: 'USER', is_active: true, phone_number: '0813' },
        token: '2|newtoken',
      },
    }

    vi.mocked(api.post).mockResolvedValue(mockResponse)

    const result = await authService.register({
      name: 'Siti',
      email: 'siti@example.com',
      password: 'password123',
      password_confirmation: 'password123',
      phone_number: '0813',
    })

    expect(api.post).toHaveBeenCalledWith('/auth/register', expect.objectContaining({ email: 'siti@example.com' }))
    expect(result.token).toBe('2|newtoken')
  })

  it('getMe requests /auth/me and returns current user', async () => {
    const mockUser = { id: 1, name: 'Budi', email: 'budi@example.com', role: 'USER', is_active: true, phone_number: '0812' }
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockUser,
    })

    const user = await authService.getMe()

    expect(api.get).toHaveBeenCalledWith('/auth/me')
    expect(user.email).toBe('budi@example.com')
  })

  it('logout triggers /auth/logout endpoint', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, message: 'Logout OK', data: null })

    await authService.logout()

    expect(api.post).toHaveBeenCalledWith('/auth/logout')
  })

  it('forgotPassword sends email to request reset link', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, message: 'Link sent', data: null })

    const result = await authService.forgotPassword('user@example.com')

    expect(api.post).toHaveBeenCalledWith('/auth/forgot-password', { email: 'user@example.com' })
    expect(result).toBe('Link sent')
  })

  it('resetPassword sends token and new password', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, message: 'Password reset', data: null })

    const payload = {
      token: 'valid-token',
      email: 'user@example.com',
      password: 'newpassword123',
      password_confirmation: 'newpassword123',
    }

    const result = await authService.resetPassword(payload)

    expect(api.post).toHaveBeenCalledWith('/auth/reset-password', payload)
    expect(result).toBe('Password reset')
  })
})
