import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminTimesheetsPage } from './pages/AdminTimesheetsPage'
import { timesheetService } from './services/timesheetService'
import { ToastProvider } from '@/app/ToastContext'
import type { Timesheet } from '@/types/timesheet'

vi.mock('./services/timesheetService', () => ({
  timesheetService: {
    getTimesheets: vi.fn(),
    getTimesheet: vi.fn(),
    create: vi.fn(),
    submit: vi.fn(),
    sign: vi.fn(),
    approve: vi.fn(),
    reject: vi.fn(),
    revise: vi.fn(),
    getRevisions: vi.fn(),
  },
}))

const submitted: Timesheet = {
  id: 3,
  rental_detail_id: 88,
  report_date: '2026-09-26',
  start_hm: 8,
  end_hm: 16,
  break_minutes: 60,
  total_work_hours: 7,
  standby_hours: 1,
  breakdown_hours: 0,
  operator_name: 'Bejo',
  notes: null,
  signature_reference: 'attachments/2/sig.png',
  status: 'SUBMITTED',
  signature: null,
  rental: {
    rental_id: 7,
    booking_code: 'RFA-BKG-20260927-0001',
    project_name: 'Tol Cisauk',
    unit_id: 55,
    unit_serial: 'KM-001',
    unit_plate: 'B 1 RFA',
  },
  created_at: '2026-09-26T18:00:00Z',
}

const renderComponent = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminTimesheetsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Admin Timesheet UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [submitted],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    } as any)
  })

  it('lists submitted timesheets with approval actions', async () => {
    renderComponent()
    expect(await screen.findByText('#3')).toBeInTheDocument()
    expect(screen.getByText('SUBMITTED')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /setujui/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /tolak/i })).toBeInTheDocument()
  })

  it('approves a submitted timesheet', async () => {
    vi.mocked(timesheetService.approve).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...submitted, status: 'APPROVED' },
    } as any)

    renderComponent()
    const actionBtn = await screen.findByRole('button', { name: /setujui/i })
    fireEvent.click(actionBtn)
    fireEvent.click(screen.getAllByRole('button', { name: /setujui/i })[1])

    await waitFor(() => {
      expect(timesheetService.approve).toHaveBeenCalledWith(3)
    })
  })

  it('rejects with required reason', async () => {
    vi.mocked(timesheetService.reject).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...submitted, status: 'REJECTED' },
    } as any)

    renderComponent()
    fireEvent.click(await screen.findByRole('button', { name: /tolak/i }))

    // Too short -> inline error, no API call
    fireEvent.change(screen.getByLabelText(/alasan penolakan/i), { target: { value: 'ab' } })
    fireEvent.click(screen.getByRole('button', { name: /tolak & perlu koreksi/i }))
    expect(await screen.findByText(/alasan penolakan minimal 5 karakter/i)).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/alasan penolakan/i), { target: { value: 'Jam kerja tidak sesuai meter' } })
    fireEvent.click(screen.getByRole('button', { name: /tolak & perlu koreksi/i }))

    await waitFor(() => {
      expect(timesheetService.reject).toHaveBeenCalledWith(3, 'Jam kerja tidak sesuai meter')
    })
  })

  it('revises an approved timesheet back to validation', async () => {
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [{ ...submitted, status: 'APPROVED' }],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    } as any)
    vi.mocked(timesheetService.revise).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...submitted, status: 'SUBMITTED', end_hm: 17 },
    } as any)

    renderComponent()
    fireEvent.click(await screen.findByRole('button', { name: /koreksi/i }))

    fireEvent.change(screen.getByLabelText(/jam akhir \(hm\)/i), { target: { value: '17' } })
    fireEvent.change(screen.getByLabelText(/alasan koreksi/i), { target: { value: 'Jam kerja diperbaiki admin' } })
    fireEvent.click(screen.getByRole('button', { name: /simpan koreksi/i }))

    await waitFor(() => {
      expect(timesheetService.revise).toHaveBeenCalledWith(
        3,
        expect.objectContaining({ end_hm: 17, reason: 'Jam kerja diperbaiki admin' })
      )
    })
  })

  it('shows empty state', async () => {
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 50, total: 0, last_page: 1 },
    } as any)

    renderComponent()
    expect(await screen.findByRole('heading', { name: /tidak ada timesheet/i })).toBeInTheDocument()
  })
})