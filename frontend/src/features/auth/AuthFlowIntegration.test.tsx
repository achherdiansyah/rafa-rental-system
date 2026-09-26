import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AuthProvider } from '@/app/AuthContext'
import { AppRoutes } from '@/routes/AppRoutes'
import { authService } from './services/authService'

vi.mock('./services/authService', () => ({
  authService: {
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    getMe: vi.fn(),
  },
}))

describe('Frontend End-to-End Authentication Journey', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('completes login, redirects to user portal, and logs out successfully', async () => {
    const mockUser = {
      id: 10,
      name: 'Rian Pratama',
      email: 'rian@perusahaan.com',
      role: 'USER' as const,
      phone_number: '081234567890',
      is_active: true,
    }

    vi.mocked(authService.login).mockResolvedValue({
      user: mockUser,
      token: 'valid_token_rian',
    })

    vi.mocked(authService.logout).mockResolvedValue()

    render(
      <MemoryRouter initialEntries={['/login']}>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </MemoryRouter>
    )

    // 1. Wait for lazy-loaded login page and fill form
    const emailInput = await screen.findByLabelText(/email/i)
    const passwordInput = await screen.findByLabelText(/^kata sandi/i)

    fireEvent.change(emailInput, { target: { value: 'rian@perusahaan.com' } })
    fireEvent.change(passwordInput, { target: { value: 'password123' } })

    // 2. Submit form
    fireEvent.click(screen.getByRole('button', { name: /masuk/i }))

    // 3. User should land on Customer Portal (/app)
    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /portal pelanggan/i })).toBeInTheDocument()
    }, { timeout: 15000 })

    // 4. Navbar shows user info
    expect(screen.getByText('Rian Pratama')).toBeInTheDocument()
    expect(screen.getByText('USER')).toBeInTheDocument()

    // 5. User clicks logout button
    const logoutBtn = screen.getByRole('button', { name: /logout/i })
    fireEvent.click(logoutBtn)

    // 6. User is redirected back to login page and storage is cleared
    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /masuk akun/i })).toBeInTheDocument()
      expect(localStorage.getItem('rafa_token')).toBeNull()
    }, { timeout: 15000 })
  }, 40000)

  it('admin login redirects directly to Admin Workspace (/admin)', async () => {
    const adminUser = {
      id: 2,
      name: 'Admin Utama',
      email: 'admin@rafarental.com',
      role: 'ADMIN' as const,
      phone_number: '08110000001',
      is_active: true,
    }

    vi.mocked(authService.login).mockResolvedValue({
      user: adminUser,
      token: 'admin_token_abc',
    })

    render(
      <MemoryRouter initialEntries={['/login']}>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </MemoryRouter>
    )

    const emailInput = await screen.findByLabelText(/email/i)
    const passwordInput = await screen.findByLabelText(/^kata sandi/i)

    fireEvent.change(emailInput, { target: { value: 'admin@rafarental.com' } })
    fireEvent.change(passwordInput, { target: { value: 'password123' } })
    fireEvent.click(screen.getByRole('button', { name: /masuk/i }))

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /meja kerja operasional/i })).toBeInTheDocument()
    }, { timeout: 10000 })

    expect(screen.getByText('Admin Utama')).toBeInTheDocument()
    expect(screen.getByText('ADMIN')).toBeInTheDocument()
  }, 15000)

  it('owner login redirects directly to Executive Dashboard (/owner)', async () => {
    const ownerUser = {
      id: 1,
      name: 'Owner Bisnis',
      email: 'owner@rafarental.com',
      role: 'OWNER' as const,
      phone_number: '08110000002',
      is_active: true,
    }

    vi.mocked(authService.login).mockResolvedValue({
      user: ownerUser,
      token: 'owner_token_xyz',
    })

    render(
      <MemoryRouter initialEntries={['/login']}>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </MemoryRouter>
    )

    const emailInput = await screen.findByLabelText(/email/i)
    const passwordInput = await screen.findByLabelText(/^kata sandi/i)

    fireEvent.change(emailInput, { target: { value: 'owner@rafarental.com' } })
    fireEvent.change(passwordInput, { target: { value: 'password123' } })
    fireEvent.click(screen.getByRole('button', { name: /masuk/i }))

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /executive dashboard/i })).toBeInTheDocument()
    }, { timeout: 10000 })

    expect(screen.getByText('Owner Bisnis')).toBeInTheDocument()
    expect(screen.getByText('OWNER')).toBeInTheDocument()
  }, 15000)
})
