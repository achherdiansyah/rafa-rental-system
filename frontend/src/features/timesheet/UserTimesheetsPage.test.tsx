import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { UserTimesheetsPage } from './pages/UserTimesheetsPage'
import { timesheetService } from './services/timesheetService'
import { rentalService } from '@/features/rental/services/rentalService'
import { ToastProvider } from '@/app/ToastContext'
import type { Timesheet } from '@/types/timesheet'
import type { Rental } from '@/types/rental'

vi.mock('./services/timesheetService', () => ({
  timesheetService: {
    getTimesheets: vi.fn(),
    create: vi.fn(),
    submit: vi.fn(),
    sign: vi.fn(),
    getRevisions: vi.fn(),
    approve: vi.fn(),
    reject: vi.fn(),
    revise: vi.fn(),
  },
}))

vi.mock('@/features/rental/services/rentalService', () => ({
  rentalService: {
    getRentals: vi.fn(),
    transition: vi.fn(),
    markReady: vi.fn(),
    getRental: vi.fn(),
    createFromBooking: vi.fn(),
  },
}))

const rentalFixture: Rental = {
  id: 7,
  booking_id: 10,
  status: 'ONGOING',
  started_at: '2026-09-25T08:00:00Z',
  completed_at: null,
  booking: {
    id: 10,
    booking_code: 'RFA-BKG-20260927-0001',
    user_id: 7,
    project_location_id: 1,
    status: 'CONFIRMED',
    project_location: { id: 1, project_name: 'Tol Cisauk', city: 'Tangerang' } as any,
    details: [],
  } as any,
  details: [
    {
      id: 88,
      rental_id: 7,
      assignment_id: 5,
      status: 'ONGOING',
      check_in_hm: null,
      check_out_hm: null,
      condition_notes: null,
      inspection_result: null,
      checked_out_at: null,
      unit: { id: 55, serial_number: 'KM-001', plate_number: 'B 1 RFA', status: 'ON_SITE' },
    },
  ],
  created_at: '2026-09-25T08:00:00Z',
  updated_at: '2026-09-25T08:00:00Z',
}

const draftTimesheet: Timesheet = {
  id: 1,
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
  signature_reference: null,
  status: 'DRAFT',
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
        <UserTimesheetsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('User Timesheet UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [draftTimesheet],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    } as any)
    vi.mocked(rentalService.getRentals).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [rentalFixture],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    } as any)
  })

  it('renders own timesheets with status and hours', async () => {
    renderComponent()
    expect(await screen.findByText('KM-001')).toBeInTheDocument()
    expect(screen.getByText('Kerja: 7 jam')).toBeInTheDocument()
    expect(screen.getByText('DRAFT')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /ajukan validasi/i })).toBeInTheDocument()
  })

  it('creates a timesheet with validation', async () => {
    vi.mocked(timesheetService.create).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...draftTimesheet, id: 9 },
    } as any)

    renderComponent()
    fireEvent.click(await screen.findByRole('button', { name: /catat timesheet/i }))

    fireEvent.change(screen.getByLabelText(/unit rental \(ongoing\)/i), { target: { value: '88' } })
    fireEvent.change(screen.getByLabelText(/jam mulai \(hm\)/i), { target: { value: '08.00' } })
    fireEvent.change(screen.getByLabelText(/jam akhir \(hm\)/i), { target: { value: '16.00' } })
    fireEvent.change(screen.getByLabelText(/nama operator/i), { target: { value: 'Bejo' } })

    fireEvent.click(screen.getByRole('button', { name: /simpan timesheet/i }))

    await waitFor(() => {
      expect(timesheetService.create).toHaveBeenCalledWith(
        expect.objectContaining({
          rental_detail_id: 88,
          start_hm: 8,
          end_hm: 16,
          operator_name: 'Bejo',
        })
      )
    })
  })

  it('blocks invalid hour range client side', async () => {
    renderComponent()
    fireEvent.click(await screen.findByRole('button', { name: /catat timesheet/i }))

    fireEvent.change(screen.getByLabelText(/unit rental \(ongoing\)/i), { target: { value: '88' } })
    fireEvent.change(screen.getByLabelText(/jam mulai \(hm\)/i), { target: { value: '16.00' } })
    fireEvent.change(screen.getByLabelText(/jam akhir \(hm\)/i), { target: { value: '08.00' } })

    fireEvent.click(screen.getByRole('button', { name: /simpan timesheet/i }))

    expect(await screen.findByText(/jam akhir harus lebih besar/i)).toBeInTheDocument()
    expect(timesheetService.create).not.toHaveBeenCalled()
  })

  it('submits a draft for validation', async () => {
    vi.mocked(timesheetService.submit).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...draftTimesheet, status: 'SUBMITTED' },
    } as any)

    renderComponent()
    fireEvent.click(await screen.findByRole('button', { name: /ajukan validasi/i }))
    fireEvent.click(screen.getAllByRole('button', { name: /ajukan/i })[1])

    await waitFor(() => {
      expect(timesheetService.submit).toHaveBeenCalledWith(1)
    })
  })

  it('uploads signature', async () => {
    vi.mocked(timesheetService.sign).mockResolvedValue({
      success: true,
      message: 'OK',
      data: {
        id: 4,
        document_type: 'TIMESHEET_SIGNATURE',
        file_name: 'sig.png',
        mime_type: 'image/png',
        file_size: 12,
        url: null,
        uploaded_by: 7,
        created_at: '2026-09-26T18:00:00Z',
      } as any,
    } as any)

    renderComponent()
    await screen.findByText('KM-001')

    const file = new File(['data'], 'sig.png', { type: 'image/png' })
    const input = screen.getByLabelText(/tanda tangan/i) as HTMLInputElement
    fireEvent.change(input, { target: { files: [file] } })

    await waitFor(() => {
      expect(timesheetService.sign).toHaveBeenCalledWith(1, file)
    })
  })

  it('shows mutation history and empty state', async () => {
    vi.mocked(timesheetService.getRevisions).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [
        {
          id: 1,
          timesheet_id: 1,
          version: 1,
          old_start_hm: 7,
          old_end_hm: 15,
          revision_reason: 'Jam meter dikoreksi',
          revised_by: 2,
          revised_by_name: 'Admin',
          created_at: '2026-09-26T18:00:00Z',
        },
      ],
    } as any)

    renderComponent()
    fireEvent.click(await screen.findByText(/riwayat revisi/i))
    expect(await screen.findByText(/jam meter dikoreksi/i)).toBeInTheDocument()
    expect(screen.getByText(/v1/i)).toBeInTheDocument()
  })

  it('shows empty state when no timesheets', async () => {
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 50, total: 0, last_page: 1 },
    } as any)

    renderComponent()
    expect(await screen.findByText(/belum ada timesheet/i)).toBeInTheDocument()
  })
})