import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminPaymentsPage } from './pages/AdminPaymentsPage'
import { invoiceService } from './services/invoiceService'
import { ToastProvider } from '@/app/ToastContext'
import type { Payment } from '@/types/invoice'

vi.mock('./services/invoiceService', () => ({
  invoiceService: {
    getInvoices: vi.fn(),
    getPayments: vi.fn(),
    submitPayment: vi.fn(),
    approvePayment: vi.fn(),
    rejectPayment: vi.fn(),
    queuePayments: vi.fn(),
    extendDeadline: vi.fn(),
    proofUrl: vi.fn(),
    fetchProofObjectUrl: vi.fn(),
  },
}))

const queued: Payment = {
  id: 5,
  invoice_id: 1,
  status: 'SUBMITTED',
  amount: 800000,
  payment_date: '2026-09-25T08:00:00Z',
  sender_name: 'PT Mitra Sejahtera',
  reference: 'TRF-1',
  rejection_reason: null,
  proof: null,
  invoice: { invoice_number: 'INV/202609/0001', status: 'ISSUED', booking_code: 'RFA-BKG-20260927-0001' },
  created_at: '2026-09-25T08:00:00Z',
  updated_at: '2026-09-25T08:00:00Z',
}

const renderComponent = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminPaymentsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Admin Payment Queue UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(invoiceService.queuePayments).mockResolvedValue({
      data: [queued],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any)
  })

  it('lists submitted payments queue', async () => {
    renderComponent()
    expect(await screen.findByText('SUBMITTED')).toBeInTheDocument()
    expect(screen.getByText(/PT Mitra Sejahtera/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /setujui/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /tolak/i })).toBeInTheDocument()
  })

  it('approves payment after confirmation', async () => {
    vi.mocked(invoiceService.approvePayment).mockResolvedValue({ ...queued, status: 'APPROVED' } as any)

    renderComponent()
    fireEvent.click(await screen.findByRole('button', { name: /setujui/i }))
    fireEvent.click(screen.getAllByRole('button', { name: /setujui/i })[1])

    await waitFor(() => {
      expect(invoiceService.approvePayment).toHaveBeenCalledWith(5)
    })
  })

  it('rejects with mandatory reason', async () => {
    vi.mocked(invoiceService.rejectPayment).mockResolvedValue({ ...queued, status: 'REJECTED' } as any)

    renderComponent()
    fireEvent.click(await screen.findByRole('button', { name: /tolak/i }))

    fireEvent.change(screen.getByLabelText(/alasan penolakan/i), { target: { value: 'ab' } })
    fireEvent.click(screen.getAllByRole('button', { name: /tolak/i })[1])
    expect(await screen.findByText(/alasan penolakan minimal 5 karakter/i)).toBeInTheDocument()
    expect(invoiceService.rejectPayment).not.toHaveBeenCalled()

    fireEvent.change(screen.getByLabelText(/alasan penolakan/i), { target: { value: 'Nominal tidak sesuai mutasi bank' } })
    fireEvent.click(screen.getAllByRole('button', { name: /tolak/i })[1])

    await waitFor(() => {
      expect(invoiceService.rejectPayment).toHaveBeenCalledWith(5, 'Nominal tidak sesuai mutasi bank')
    })
  })
})