import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminInvoicesPage } from './pages/AdminInvoicesPage'
import { invoiceService } from './services/invoiceService'
import { ToastProvider } from '@/app/ToastContext'
import type { Invoice } from '@/types/invoice'

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

const overdueInv: Invoice = {
  id: 4,
  invoice_number: 'INV/202609/0004',
  invoice_type: 'MOB_DEMOB',
  booking_id: 7,
  status: 'OVERDUE',
  issued_at: '2026-09-24T09:00:00Z',
  due_at: '2026-09-25T09:00:00Z',
  subtotal: 850000,
  tax_total: 0,
  grand_total: 850000,
  paid_amount: 0,
  balance_amount: 850000,
  booking: { booking_code: 'RFA-BKG-20260901-0007', project: { id: 2, project_name: 'Jalan Tol B', city: 'Jakarta' } },
  details: [],
  created_at: '2026-09-24T09:00:00Z',
  updated_at: '2026-09-24T09:00:00Z',
}

const renderComponent = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminInvoicesPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Admin Invoice UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(invoiceService.getInvoices).mockResolvedValue({
      data: [overdueInv],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any)
  })

  it('renders invoice with balance and deadline', async () => {
    renderComponent()
    expect(await screen.findByText('INV/202609/0004')).toBeInTheDocument()
    expect(screen.getByText('OVERDUE')).toBeInTheDocument()
    expect(screen.getAllByText(/25\/9\/2026/).length).toBeGreaterThan(0)
  })

  it('extends deadline with audit notice', async () => {
    vi.mocked(invoiceService.extendDeadline).mockResolvedValue({ ...overdueInv, due_at: '2026-09-27T09:00:00Z' } as any)

    renderComponent()
    fireEvent.click(await screen.findByRole('button', { name: /perpanjang deadline/i }))
    fireEvent.change(screen.getByLabelText(/tambah jam/i), { target: { value: '48' } })
    fireEvent.click(screen.getAllByRole('button', { name: /perpanjang/i })[1])

    await waitFor(() => {
      expect(invoiceService.extendDeadline).toHaveBeenCalledWith(4, 48)
    })
  })
})