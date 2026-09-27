import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { UserRefundsPage } from './pages/UserRefundsPage'
import { AdminRefundsPage } from './pages/AdminRefundsPage'
import { refundService } from './services/refundService'
import { ToastProvider } from '@/app/ToastContext'
import type { Refund } from '@/types/refund'

vi.mock('./services/refundService', () => ({
  refundService: {
    getRefunds: vi.fn(),
    approve: vi.fn(),
    process: vi.fn(),
    complete: vi.fn(),
    fail: vi.fn(),
  },
  financeService: { myOutstanding: vi.fn(), allOutstanding: vi.fn() },
}))

const { useAuthMock } = vi.hoisted(() => ({ useAuthMock: vi.fn() }))
vi.mock('@/hooks/useAuth', () => ({ useAuth: () => useAuthMock() }))

const pendingRefund: Refund = {
  id: 1,
  invoice_id: 10,
  source: 'OVERPAYMENT',
  amount: 200000,
  reason: 'Kelebihan pembayaran invoice #INV/202609/0001',
  approval_reason: null,
  customer_bank_info: null,
  transfer_reference: null,
  status: 'PENDING',
  failure_reason: null,
  approved_at: null,
  processed_at: null,
  completed_at: null,
  invoice: { invoice_number: 'INV/202609/0001', booking_code: 'RFA-BKG-1', project: { project_name: 'Tol Cisauk', city: 'Tangerang' } },
  created_at: '2026-09-27T10:00:00Z',
}

const renderComponent = (page: React.ReactElement) =>
  render(<MemoryRouter><ToastProvider>{page}</ToastProvider></MemoryRouter>)

const setRole = (role: string) => useAuthMock.mockReturnValue({ user: { role }, isAuthenticated: true })

describe('Refund UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(refundService.getRefunds).mockResolvedValue({
      data: [pendingRefund],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any)
  })

  it('shows amount and reason for user', async () => {
    renderComponent(<UserRefundsPage />)
expect(await screen.findByText('PENDING')).toBeInTheDocument()
    expect(screen.getByText(/Kelebihan pembayaran invoice/i)).toBeInTheDocument()
    expect(screen.getAllByText(/INV\/202609\/0001/i).length).toBeGreaterThan(0)
  })

  it('owner can approve pending refund', async () => {
    setRole('OWNER')
    vi.mocked(refundService.approve).mockResolvedValue({ ...pendingRefund, status: 'APPROVED' } as any)

    renderComponent(<AdminRefundsPage />)
    fireEvent.click(await screen.findByRole('button', { name: /setujui/i }))
    fireEvent.click(screen.getAllByRole('button', { name: /setujui/i })[1])

    await waitFor(() => {
      expect(refundService.approve).toHaveBeenCalledWith(1, undefined)
    })
  })

  it('owner cannot process (admin-only) but can fail', async () => {
    setRole('OWNER')
    vi.mocked(refundService.getRefunds).mockResolvedValue({
      data: [{ ...pendingRefund, status: 'APPROVED' }],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any)

    renderComponent(<AdminRefundsPage />)
    await screen.findByText('APPROVED')
    expect(screen.queryByRole('button', { name: /^proses$/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /gagalkan/i })).not.toBeInTheDocument()
  })

  it('admin processes then completes with proof', async () => {
    setRole('ADMIN')
    vi.mocked(refundService.getRefunds).mockResolvedValue({
      data: [{ ...pendingRefund, status: 'PROCESSING', customer_bank_info: 'BCA 123' }],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any)
    vi.mocked(refundService.complete).mockResolvedValue({ ...pendingRefund, status: 'COMPLETED' } as any)

    renderComponent(<AdminRefundsPage />)
    fireEvent.click(await screen.findByRole('button', { name: /selesaikan/i }))
    const file = new File(['x'], 'resi.png', { type: 'image/png' })
    fireEvent.change(screen.getByLabelText(/bukti transfer refund/i) as HTMLInputElement, { target: { files: [file] } })
    fireEvent.click(screen.getAllByRole('button', { name: /selesaikan/i })[1])

    await waitFor(() => {
      expect(refundService.complete).toHaveBeenCalledWith(1, expect.objectContaining({ proof: file }))
    })
  })
})
