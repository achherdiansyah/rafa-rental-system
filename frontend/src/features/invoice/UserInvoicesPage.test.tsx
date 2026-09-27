import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { UserInvoicesPage } from './pages/UserInvoicesPage'
import { invoiceService } from './services/invoiceService'
import { bankService } from '@/features/bank/services/bankService'
import { ToastProvider } from '@/app/ToastContext'
import type { Invoice, Payment } from '@/types/invoice'

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

vi.mock('@/features/bank/services/bankService', () => ({
  bankService: { getAccounts: vi.fn() },
}))

const invoiceFixture: Invoice = {
  id: 1,
  invoice_number: 'INV/202609/0001',
  invoice_type: 'DAILY_WORK',
  booking_id: 10,
  status: 'ISSUED',
  issued_at: '2026-09-25T09:00:00Z',
  due_at: '2026-09-26T09:00:00Z',
  subtotal: 800000,
  tax_total: 0,
  grand_total: 800000,
  paid_amount: 0,
  balance_amount: 800000,
  booking: { booking_code: 'RFA-BKG-20260927-0001', project: { id: 1, project_name: 'Tol Cisauk', city: 'Tangerang' } },
  details: [{ id: 1, description: 'Sewa Harian KM-001 — 8.00 jam @ 100000', unit_price: 100000, quantity: 8, subtotal: 800000 }],
  created_at: '2026-09-25T09:00:00Z',
  updated_at: '2026-09-25T09:00:00Z',
}

const paidPartial: Invoice = {
  ...invoiceFixture,
  id: 2,
  invoice_number: 'INV/202609/0002',
  status: 'PARTIALLY_PAID',
  paid_amount: 300000,
  balance_amount: 500000,
}

const overpaid: Invoice = {
  ...invoiceFixture,
  id: 3,
  invoice_number: 'INV/202609/0003',
  status: 'OVERPAID',
  paid_amount: 800000,
  balance_amount: 0,
  overpayment_amount: 200000,
}

const rejectedPayment: Payment = {
  id: 9,
  invoice_id: 2,
  status: 'REJECTED',
  amount: 50000,
  payment_date: '2026-09-25T08:00:00Z',
  sender_name: 'PT Mitra',
  reference: 'TRF-X',
  rejection_reason: 'Nominal tidak cocok dengan mutasi bank.',
  proof: null,
  created_at: '2026-09-25T08:00:00Z',
  updated_at: '2026-09-25T08:30:00Z',
}

const renderComponent = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <UserInvoicesPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('User Invoice UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(invoiceService.getInvoices).mockResolvedValue({
      data: [invoiceFixture, paidPartial, overpaid],
      meta: { total: 3, current_page: 1, per_page: 50, last_page: 1 },
    } as any)
    vi.mocked(invoiceService.getPayments).mockResolvedValue([rejectedPayment] as any)
    vi.mocked(bankService.getAccounts).mockResolvedValue([
      { id: 1, bank_name: 'Bank BCA', account_number: '1234', account_name: 'RAFA', is_active: true },
    ] as any)
  })

  it('renders invoices with balance and overpayment notice', async () => {
    renderComponent()
    expect(await screen.findByText('INV/202609/0001')).toBeInTheDocument()
    expect(screen.getByText(/Saldo: Rp\s*800\.000,00/)).toBeInTheDocument()
    expect(screen.getByText(/Kelebihan bayar/i)).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: /bayar sekarang/i }).length).toBe(2)
  })

  it('shows payment history with rejected reason on expand', async () => {
    renderComponent()
    fireEvent.click(await screen.findByText('INV/202609/0002'))
    expect(await screen.findByText(/nominal tidak cocok dengan mutasi bank/i)).toBeInTheDocument()
    expect(screen.getByText('REJECTED')).toBeInTheDocument()
  })

  it('validates and submits payment upload', async () => {
    vi.mocked(invoiceService.submitPayment).mockResolvedValue({ ...rejectedPayment, status: 'SUBMITTED' } as any)

    renderComponent()
    fireEvent.click((await screen.findAllByRole('button', { name: /bayar sekarang/i }))[0])

    // Missing proof -> inline validation, no API call
    fireEvent.click(screen.getByRole('button', { name: /ajukan bukti/i }))
    expect(await screen.findByText(/bukti transfer wajib dilampirkan/i)).toBeInTheDocument()
    expect(invoiceService.submitPayment).not.toHaveBeenCalled()

    // Fill valid form
    fireEvent.change(screen.getByLabelText(/nominal transfer/i), { target: { value: '800000' } })
    fireEvent.change(screen.getByLabelText(/rekening tujuan/i), { target: { value: '1' } })
    const file = new File(['x'], 'bukti.png', { type: 'image/png' })
    fireEvent.change(screen.getByLabelText(/bukti transfer/i) as HTMLInputElement, { target: { files: [file] } })

    fireEvent.click(screen.getByRole('button', { name: /ajukan bukti/i }))

    await waitFor(() => {
      expect(invoiceService.submitPayment).toHaveBeenCalledWith(
        1,
        expect.objectContaining({ amount: 800000, bank_account_id: 1, proof: file })
      )
    })
  })
})