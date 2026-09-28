import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AuthProvider } from './AuthContext'
import { useAuth } from '@/hooks/useAuth'
import { authService } from '@/features/auth/services/authService'
import type { AuthUser } from '@/types/auth'

vi.mock('@/features/auth/services/authService', () => ({
  authService: {
    getMe: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    requestPasswordReset: vi.fn(),
    resetPassword: vi.fn(),
  },
}))

const user: AuthUser = {
  id: 1,
  name: 'Dono',
  email: 'dono@example.com',
  role: 'USER',
  phone_number: '0812',
  is_active: true,
}

const Probe: React.FC = () => {
  const { isAuthenticated, login } = useAuth()
  return (
    <div>
      <span data-testid="auth-state">{String(isAuthenticated)}</span>
      <button
        onClick={() =>
          login({ email: user.email, password: 'password123' }).catch(() => undefined)
        }
      >
        login
      </button>
    </div>
  )
}

const renderApp = () =>
  render(
    <MemoryRouter initialEntries={['/app']}>
      <AuthProvider>
        <Probe />
      </AuthProvider>
    </MemoryRouter>
  )

describe('AuthProvider 401 session-expiry handling', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    vi.mocked(authService.login).mockResolvedValue({ token: 'tok-1', user })
    vi.mocked(authService.getMe).mockResolvedValue(user)
  })

  it('clears the session state when rafa:auth-expired fires (no full-page reload)', async () => {
    renderApp()

    fireEvent.click(screen.getByText('login'))
    await waitFor(() => expect(screen.getByTestId('auth-state').textContent).toBe('true'))
    expect(localStorage.getItem('rafa_token')).toBe('tok-1')

    // Simulates the API 401 interceptor after manual navigation guard tests
    fireEvent(
      window,
      new CustomEvent('rafa:auth-expired')
    )

    await waitFor(() => {
      expect(screen.getByTestId('auth-state').textContent).toBe('false')
      expect(localStorage.getItem('rafa_token')).toBeNull()
      expect(localStorage.getItem('rafa_user')).toBeNull()
    })
  })
})