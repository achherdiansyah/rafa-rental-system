import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminFinancialReportsPage } from './pages/AdminFinancialReportsPage'
import { reportingService } from './services/reportingService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/reportingService', () => ({
  reportingService: {
    getDashboard: vi.fn(),
    listReport: vi.fn(),
    downloadCsv: vi.fn().mockResolvedValue('blob:fin'),
  },
}))

const invoiceRow = {
  id: 1,
  invoice_number: 'INV/202609/0001',
  status: 'UNPAID',
  customer_name: 'PT Mitra Sejahtera',
  grand_total: 800000,
  paid_total: 0,
  balance: 800000,
}

const renderPage = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminFinancialReportsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Financial Reports UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(reportingService.listReport).mockResolvedValue({
      data: [invoiceRow],
      meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
    } as any)
  })

  it('loads invoices tab with single request (no waterfall)', async () => {
    renderPage()
    expect(await screen.findByText('INV/202609/0001')).toBeInTheDocument()
    expect(reportingService.listReport).toHaveBeenCalledTimes(1)
    expect(reportingService.listReport).toHaveBeenCalledWith('invoices', expect.any(Object))
  })

  it('switches tab loads only selected report', async () => {
    renderPage()
    await screen.findByText('INV/202609/0001')

    fireEvent.click(screen.getByRole('tab', { name: /refund/i }))
    await waitFor(() => {
      expect(reportingService.listReport).toHaveBeenCalledWith('refunds', expect.any(Object))
    })
    expect(reportingService.listReport).toHaveBeenCalledTimes(2)
  })

  it('exports CSV for the active financial report', async () => {
    renderPage()
    await screen.findByText('INV/202609/0001')

    fireEvent.click(screen.getByRole('button', { name: /ekspor csv/i }))

    await waitFor(() => {
      expect(reportingService.downloadCsv).toHaveBeenCalledWith('invoices', expect.any(Object))
    })
  })
})