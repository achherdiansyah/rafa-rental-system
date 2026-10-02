import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { UserTimesheetsPage } from './pages/UserTimesheetsPage'
import { timesheetService } from './services/timesheetService'
import { ToastProvider } from '@/app/ToastContext'
import type { Timesheet } from '@/types/timesheet'

vi.mock('./services/timesheetService', () => ({
  timesheetService: {
    getTimesheets: vi.fn(),
    sign: vi.fn(),
    getRevisions: vi.fn(),
    create: vi.fn(),
    submit: vi.fn(),
    approve: vi.fn(),
    reject: vi.fn(),
    revise: vi.fn(),
  },
}))

const submitted: Timesheet = {
  id: 3,
  rental_detail_id: 88,
  report_date: '2026-09-26',
  start_time: '08:00',
  end_time: '16:00',
  start_hm: 8,
  end_hm: 16,
  break_minutes: 60,
  total_work_hours: 7,
  standby_hours: 1,
  breakdown_hours: 0,
  operator_name: 'Bejo',
  notes: null,
  signature_reference: null,
  status: 'SUBMITTED',
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

const approved: Timesheet = { ...submitted, id: 4, status: 'APPROVED', signature_reference: 'att-sig-1' }

const createFile = () => new File(['sig'], 'ttd.png', { type: 'image/png' })

const renderComponent = () =>
  render(
    <ToastProvider>
      <UserTimesheetsPage />
    </ToastProvider>
  )

describe('User Timesheet UI (read + confirm only)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [submitted, approved] as never,
      meta: { current_page: 1, per_page: 50, total: 2, last_page: 1 },
    } as never)
    vi.mocked(timesheetService.getRevisions).mockResolvedValue({ success: true, message: 'OK', data: [] as never } as never)
  })

  it('renders timesheets with status, hours, and subtitle (no create entry point)', async () => {
    renderComponent()

    expect((await screen.findAllByText('KM-001')).length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('Menunggu Konfirmasi Anda').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('Tervalidasi').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('7 jam').length).toBeGreaterThanOrEqual(1)

    // USER cannot create/edit: no "Catat Timesheet", no "Simpan Timesheet"
    expect(screen.queryByText('Catat Timesheet')).not.toBeInTheDocument()
    expect(screen.queryByText('Simpan Timesheet')).not.toBeInTheDocument()
    expect(timesheetService.create).not.toHaveBeenCalled()
    expect(timesheetService.submit).not.toHaveBeenCalled()
  })

  it('shows detail modal with actual working hours', async () => {
    renderComponent()
    fireEvent.click((await screen.findAllByText('Detail'))[0])

    // Modal-only labels prove the details dialog opened
    expect(await screen.findByText('Standby / Breakdown')).toBeInTheDocument()
    expect(screen.getAllByText('Actual Working Hours').length).toBeGreaterThanOrEqual(1)
  })

  it('signs (confirms) a submitted timesheet via file upload', async () => {
    vi.mocked(timesheetService.sign).mockResolvedValue({ success: true, message: 'OK', data: {} as never } as never)

    renderComponent()
    const signBtn = await screen.findByText('Konfirmasi & Tanda Tangan')
    fireEvent.click(signBtn)

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    expect(input).not.toBeNull()
    fireEvent.change(input, { target: { files: [createFile()] } })

    await waitFor(() => {
      expect(timesheetService.sign).toHaveBeenCalledTimes(1)
    })
  })

  it('does not offer sign for already approved timesheets', async () => {
    renderComponent()
    await screen.findAllByText('KM-001')
    // Only the SUBMITTED row renders a file input; the APPROVED row does not.
    // (two rows total: one submitted, one approved)
    expect(document.querySelectorAll('input[type="file"]').length).toBe(1)
  })

  it('renders empty state when there are no timesheets', async () => {
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [] as never,
      meta: { current_page: 1, per_page: 50, total: 0, last_page: 1 },
    } as never)

    renderComponent()

    await waitFor(() => {
      expect(screen.getByText(/belum ada timesheet/i)).toBeInTheDocument()
    })
  })
})