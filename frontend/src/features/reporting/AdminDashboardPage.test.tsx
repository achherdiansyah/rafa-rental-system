import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminDashboardPage } from './pages/AdminDashboardPage'
import { reportingService } from './services/reportingService'
import { ToastProvider } from '@/app/ToastContext'
import type { DashboardReport } from '@/types/reporting'

vi.mock('./services/reportingService', () => ({
  reportingService: { getDashboard: vi.fn() },
}))

const fixture: DashboardReport = {
  period: { from: '2026-09-01', to: '2026-09-30' },
  bookings: { total: 12, by_status: { CONFIRMED: 4, DRAFT: 8 } },
  rentals: { total: 3, active: 1, by_status: { ONGOING: 1, COMPLETED: 2 }, by_project: [{ project_id: 1, project_name: 'Tol Cisauk', total: 2 }] },
  timesheet: { total_hours: 96.5, by_month: [{ month: '2026-09', total_hours: 96.5 }] },
  equipment: {
    fleet_total: 30, available: 18, in_use: 9, maintenance: 3, utilization_hours: 96.5,
    top_models: [{ model_id: 1, model: 'Komatsu PC200', total_hours: 96.5, unit_count: 2, rental_lines: 4 }],
  },
  financial: {
    invoices: [{ status: 'UNPAID', count: 3, grand_total: 2100000, paid_total: 0, balance: 2100000 }],
    payments: { approved_count: 5, approved_amount: 4500000 },
    refunds: [{ status: 'COMPLETED', count: 1, amount: 200000 }],
  },
  outstanding: { customer_count: 2, total: 3100000 },
}

const renderPage = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminDashboardPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Admin Dashboard UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(reportingService.getDashboard).mockResolvedValue(fixture as any)
  })

  it('renders KPIs and breakdowns from single report call', async () => {
    renderPage()

    expect(await screen.findByText('12')).toBeInTheDocument()
    expect(screen.getAllByText('96,5').length).toBeGreaterThan(0)
    expect(screen.getByText(/18\/30/)).toBeInTheDocument()
    expect(screen.getByText('Tol Cisauk')).toBeInTheDocument()
    expect(screen.getAllByText(/2\.100\.000/).length).toBeGreaterThan(0)
    expect(reportingService.getDashboard).toHaveBeenCalledTimes(1)
  })

  it('applies period filter and refetches without reload', async () => {
    renderPage()
    await screen.findByText('12')

    fireEvent.change(screen.getByLabelText(/sampai/i), { target: { value: '2026-10-05' } })
    fireEvent.click(screen.getByRole('button', { name: /terapkan/i }))

    await waitFor(() => {
      expect(reportingService.getDashboard).toHaveBeenCalledWith(
        expect.objectContaining({ to: '2026-10-05' })
      )
    })
    expect(reportingService.getDashboard).toHaveBeenCalledTimes(2)
  })

  it('reset clears period params', async () => {
    renderPage()
    await screen.findByText('12')

    fireEvent.click(screen.getByRole('button', { name: /reset/i }))

    await waitFor(() => {
      expect(reportingService.getDashboard).toHaveBeenLastCalledWith({})
    })
  })
})