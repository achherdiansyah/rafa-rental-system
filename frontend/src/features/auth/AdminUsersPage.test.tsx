import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminUsersPage } from './pages/AdminUsersPage'
import { userService } from './services/userService'
import { ToastProvider } from '@/app/ToastContext'
import type { AdminUserData } from './services/userService'

vi.mock('./services/userService', () => ({
  userService: {
    getUsers: vi.fn(),
    getUser: vi.fn(),
    verifyAccount: vi.fn(),
  },
}))

const mockUsers: AdminUserData[] = [
  {
    id: 1,
    name: 'Budi Santoso',
    email: 'budi@kontraktor.com',
    role: 'USER',
    phone_number: '081234567890',
    is_active: true,
    customer_profile: {
      company_name: 'PT Maju Konstruksi',
      identity_type: 'KTP',
      identity_number: '3201234567890001',
      address: 'Jl. Sudirman No. 12',
      verification_status: 'UNVERIFIED',
    },
    created_at: '2026-09-01T08:00:00Z',
  },
]

const renderComponent = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminUsersPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('AdminUsersPage UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(userService.getUsers).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockUsers,
    } as any)
  })

  it('renders unverified user and shows Verifikasi Akun button', async () => {
    renderComponent()

    expect(await screen.findByText('Budi Santoso')).toBeInTheDocument()
    expect(screen.getByText('081234567890')).toBeInTheDocument()
    expect(screen.getByText('PT Maju Konstruksi')).toBeInTheDocument()
    expect(screen.getByText('Belum Verifikasi')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /verifikasi akun/i })).toBeInTheDocument()
  })

  it('triggers verification confirm modal and calls verifyAccount API', async () => {
    vi.mocked(userService.verifyAccount).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { user_id: 1, verification_status: 'VERIFIED' },
    } as any)

    renderComponent()

    const verifyBtn = await screen.findByRole('button', { name: /verifikasi akun/i })
    fireEvent.click(verifyBtn)

    // Modal opens with confirm button
    const confirmBtn = screen.getAllByRole('button', { name: /verifikasi akun/i })[1]
    fireEvent.click(confirmBtn)

    await waitFor(() => {
      expect(userService.verifyAccount).toHaveBeenCalledWith(1, 'VERIFIED')
    })
  })
})
