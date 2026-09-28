import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminOperationalReportsPage } from './pages/AdminOperationalReportsPage'
import { reportingService } from './services/reportingService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/reportingService', () => ({
  reportingService: {
    getDashboard: vi.fn(),
    listReport: vi.fn(),
    downloadCsv: vi.fn().mockResolvedValue('blob:download'),
  },
}))

const row = {
  id: 21,
  booking_code: 'RFA-BKG-20260927-0001',
  status: 'CONFIRMED',
  customer_name: 'PT Mitra Sejahtera',
  project_name: 'Tol Cisauk',
  total_amount: 1000000,
  created_at: '2026-09-27T10:00:00Z',
}

const renderPage = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminOperationalReportsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Operational Reports UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(reportingService.listReport).mockResolvedValue({
      data: [row],
      meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
    } as any)
  })

  it('loads only active tab and renders table rows', async () => {
    renderPage()
    expect(await screen.findByText('RFA-BKG-20260927-0001')).toBeInTheDocument()
    expect(screen.getByText('Tol Cisauk')).toBeInTheDocument()
    expect(reportingService.listReport).toHaveBeenCalledTimes(1)
    expect(reportingService.listReport).toHaveBeenCalledWith('bookings', expect.objectContaining({ per_page: 20 }))
  })

  it('applies status filter and refetches', async () => {
    renderPage()
    await screen.findByText('RFA-BKG-20260927-0001')

    fireEvent.change(screen.getByLabelText(/status/i), { target: { value: 'CONFIRMED' } })
    fireEvent.click(screen.getByRole('button', { name: /terapkan/i }))

    await waitFor(() => {
      expect(reportingService.listReport).toHaveBeenLastCalledWith('bookings', expect.objectContaining({ status: 'CONFIRMED' }))
    })
  })

  it('sorts by column and paginates', async () => {
    renderPage()
    await screen.findByText('RFA-BKG-20260927-0001')

    fireEvent.click(screen.getByText('Total'))
    await waitFor(() => {
      expect(reportingService.listReport).toHaveBeenLastCalledWith('bookings', expect.objectContaining({ sort_by: 'total_amount', sort_dir: 'asc' }))
    })
  })

  it('exports CSV with active type and params', async () => {
    renderPage()
    await screen.findByText('RFA-BKG-20260927-0001')

    fireEvent.click(screen.getByRole('button', { name: /ekspor csv/i }))

    await waitFor(() => {
      expect(reportingService.downloadCsv).toHaveBeenCalledWith('bookings', expect.objectContaining({ page: 1 }))
    })
  })

  it('switches tab and shows empty state on empty dataset', async () => {
    vi.mocked(reportingService.listReport).mockResolvedValueOnce({
      data: [row],
      meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
    } as any)

    renderPage()
    await screen.findByText('RFA-BKG-20260927-0001')

    vi.mocked(reportingService.listReport).mockResolvedValue({
      data: [],
      meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
    } as any)

    fireEvent.click(screen.getByRole('tab', { name: /timesheet/i }))
    expect(await screen.findByText(/tidak ada data/i)).toBeInTheDocument()
    expect(reportingService.listReport).toHaveBeenCalledWith('timesheets', expect.any(Object))
  })
})