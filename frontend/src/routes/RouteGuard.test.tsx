import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AuthContext } from '@/app/AuthContext'
import { AppRoutes } from './AppRoutes'
import type { AuthContextType, AuthUser } from '@/types/auth'

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

const renderWithAuth = (
  initialRoute: string,
  authOverrides: Partial<AuthContextType> = {}
) => {
  const defaultAuth: AuthContextType = {
    user: null,
    token: null,
    status: 'unauthenticated',
    isAuthenticated: false,
    isChecking: false,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    refreshUser: vi.fn(),
    ...authOverrides,
  }

  return render(
    <MemoryRouter initialEntries={[initialRoute]}>
      <AuthContext.Provider value={defaultAuth}>
        <AppRoutes />
      </AuthContext.Provider>
    </MemoryRouter>
  )
}

describe('Route Guard & Access Control', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('redirects unauthenticated user from protected route to login', async () => {
    renderWithAuth('/app')

    // Should redirect to LoginPage
    expect(await screen.findByRole('heading', { name: /masuk akun/i })).toBeInTheDocument()
  })

  it('displays loading state during authentication check and hides protected content', () => {
    renderWithAuth('/app', { isChecking: true, status: 'checking' })

    expect(screen.getByText(/memeriksa autentikasi/i)).toBeInTheDocument()
    expect(screen.queryByText(/portal pelanggan/i)).not.toBeInTheDocument()
  })

  it('allows authenticated USER to access customer portal /app', async () => {
    const user: AuthUser = { id: 1, name: 'Budi Pelanggan', email: 'budi@test.com', role: 'USER', is_active: true, phone_number: '0812' }
    renderWithAuth('/app', {
      user,
      token: 'valid_token',
      status: 'authenticated',
      isAuthenticated: true,
    })

    expect(await screen.findByRole('heading', { name: /portal pelanggan/i })).toBeInTheDocument()
  })

  it('blocks authenticated USER from accessing /admin and redirects to /forbidden', async () => {
    const user: AuthUser = { id: 1, name: 'Budi Pelanggan', email: 'budi@test.com', role: 'USER', is_active: true, phone_number: '0812' }
    renderWithAuth('/admin', {
      user,
      token: 'valid_token',
      status: 'authenticated',
      isAuthenticated: true,
    })

    expect(await screen.findByRole('heading', { name: /akses ditolak/i })).toBeInTheDocument()
    expect(screen.queryByText(/meja kerja operasional/i)).not.toBeInTheDocument()
  })

  it('allows authenticated ADMIN to access /admin', async () => {
    const admin: AuthUser = { id: 2, name: 'Admin Operasional', email: 'admin@test.com', role: 'ADMIN', is_active: true, phone_number: '0811' }
    renderWithAuth('/admin', {
      user: admin,
      token: 'valid_admin_token',
      status: 'authenticated',
      isAuthenticated: true,
    })

    expect(await screen.findByRole('heading', { name: /meja kerja operasional/i })).toBeInTheDocument()
  })

  it('blocks authenticated ADMIN from accessing /owner and redirects to /forbidden', async () => {
    const admin: AuthUser = { id: 2, name: 'Admin Operasional', email: 'admin@test.com', role: 'ADMIN', is_active: true, phone_number: '0811' }
    renderWithAuth('/owner', {
      user: admin,
      token: 'valid_admin_token',
      status: 'authenticated',
      isAuthenticated: true,
    })

    expect(await screen.findByRole('heading', { name: /akses ditolak/i })).toBeInTheDocument()
  })

  it('allows authenticated OWNER to access /owner', async () => {
    const owner: AuthUser = { id: 3, name: 'Owner Eksekutif', email: 'owner@test.com', role: 'OWNER', is_active: true, phone_number: '0810' }
    renderWithAuth('/owner', {
      user: owner,
      token: 'valid_owner_token',
      status: 'authenticated',
      isAuthenticated: true,
    })

    expect(await screen.findByRole('heading', { name: /executive dashboard/i })).toBeInTheDocument()
  })

  it('redirects authenticated user away from /login to respective portal via GuestRoute', async () => {
    const user: AuthUser = { id: 1, name: 'Budi', email: 'budi@test.com', role: 'USER', is_active: true, phone_number: '0812' }
    renderWithAuth('/login', {
      user,
      token: 'valid_token',
      status: 'authenticated',
      isAuthenticated: true,
    })

    // GuestRoute redirects USER to /app
    expect(await screen.findByRole('heading', { name: /portal pelanggan/i })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: /masuk akun/i })).not.toBeInTheDocument()
  })
})
