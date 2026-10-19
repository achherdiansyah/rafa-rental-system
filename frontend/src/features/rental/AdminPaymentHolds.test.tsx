import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminPaymentHoldsPage } from './pages/AdminPaymentHoldsPage'
import { api } from '@/lib/api'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}))

const holds = [
  {
    id: 1, rental_id: 11, booking_code: 'RFA-BKG-1', project_name: 'Proyek A', status: 'ACTIVE',
    reason_code: 'INVOICE_COUNT_3', trigger_invoice_id: 21, trigger_invoice_number: 'INV/1',
    outstanding_amount: 3000000, started_at: '2026-10-18T08:00:00Z', resolved_at: null,
    resolution_reason: null, created_by_name: null, resolved_by_name: null,
  },
  {
    id: 2, rental_id: 12, booking_code: 'RFA-BKG-2', project_name: 'Proyek B', status: 'RESOLVED',
    reason_code: 'OLDEST_INVOICE_72H', trigger_invoice_id: 22, trigger_invoice_number: 'INV/2',
    outstanding_amount: 1000000, started_at: '2026-10-15T08:00:00Z', resolved_at: '2026-10-16T08:00:00Z',
    resolution_reason: 'Invoice sudah lunas.', created_by_name: 'Admin', resolved_by_name: 'Admin',
  },
]

const renderPage = () => render(<MemoryRouter><AdminPaymentHoldsPage /></MemoryRouter>)

describe('Admin payment hold history', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(api.get).mockResolvedValue({ success: true, message: 'OK', data: holds } as any)
  })

  it('shows active and resolved holds with invoice and actors', async () => {
    renderPage()
    expect(await screen.findByText('RFA-BKG-1')).toBeInTheDocument()
    expect(screen.getByText('RFA-BKG-2')).toBeInTheDocument()
    expect(screen.getByText('INV/1')).toBeInTheDocument()
    expect(screen.getByText(/Invoice sudah lunas/)).toBeInTheDocument()
    expect(screen.getAllByText('ACTIVE').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('RESOLVED').length).toBeGreaterThanOrEqual(1)
  })

  it('filters by status and booking code', async () => {
    renderPage()
    await screen.findByText('RFA-BKG-1')

    fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'RESOLVED' } })
    expect(screen.queryByText('RFA-BKG-1')).not.toBeInTheDocument()
    expect(screen.getByText('RFA-BKG-2')).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Status'), { target: { value: '' } })
    fireEvent.change(screen.getByLabelText(/cari rental/i), { target: { value: 'RFA-BKG-1' } })
    expect(screen.getByText('RFA-BKG-1')).toBeInTheDocument()
    expect(screen.queryByText('RFA-BKG-2')).not.toBeInTheDocument()
  })
})
