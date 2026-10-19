import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { OperatorTimesheetsPage } from './pages/OperatorTimesheetsPage'
import { operatorService } from './services/operatorService'
import { timesheetService } from '@/features/timesheet/services/timesheetService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/operatorService', () => ({
  operatorService: { getAssignments: vi.fn(), getDashboard: vi.fn(), getAssignment: vi.fn() },
}))
vi.mock('@/features/timesheet/services/timesheetService', () => ({
  timesheetService: {
    getTimesheets: vi.fn(), create: vi.fn(), uploadPaper: vi.fn(), submit: vi.fn(), getPaperPhoto: vi.fn(),
  },
}))

const assignment = {
  id: 1, rental_id: 2, rental_detail_id: 3, rental_status: 'ONGOING', booking_code: 'RFA-BKG-1',
  project_location: { id: 4, project_name: 'Proyek A', address: 'Jl. A', city: 'Bandung', latitude: -6.9, longitude: 107.6, pic_name: null, pic_phone: null },
  unit: { id: 5, serial_number: 'KM-001', plate_number: 'B 1', status: 'ON_SITE' },
  status: 'ACTIVE', started_at: null, ended_at: null,
}

const renderPage = (history = false) => render(
  <MemoryRouter>
    <ToastProvider>
      <OperatorTimesheetsPage history={history} />
    </ToastProvider>
  </MemoryRouter>
)

describe('Operator Timesheet UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(operatorService.getAssignments).mockResolvedValue({ success: true, message: 'OK', data: [assignment] } as any)
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({ success: true, message: 'OK', data: [] } as any)
  })

  it('shows only an active ONGOING assignment and requires a photo', async () => {
    renderPage()
    expect(await screen.findByText(/KM-001/)).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/tanggal kerja/i), { target: { value: '2026-10-18' } })
    fireEvent.change(screen.getByLabelText(/jam mulai/i), { target: { value: '08:00' } })
    fireEvent.change(screen.getByLabelText(/jam selesai/i), { target: { value: '17:00' } })
    fireEvent.click(screen.getByRole('button', { name: /kirim untuk validasi/i }))

    await waitFor(() => expect(timesheetService.create).not.toHaveBeenCalled())
  })

  it('shows rejection reason and resubmit path', async () => {
    vi.mocked(timesheetService.getTimesheets).mockResolvedValue({
      success: true, message: 'OK', data: [{
        id: 9, rental_detail_id: 3, report_date: '2026-10-18', start_time: '08:00', end_time: '17:00',
        start_hm: null, end_hm: null, break_minutes: 0, total_work_hours: 9, standby_hours: 0,
        breakdown_hours: 0, operator_name: 'Op', notes: null, signature_reference: null,
        status: 'REJECTED', rejection_reason: 'Jam tidak sesuai form kertas.',
        rental: { rental_id: 2, booking_code: 'RFA-BKG-1', project_name: 'Proyek A', unit_id: 5, unit_serial: 'KM-001', unit_plate: 'B 1' },
        created_at: '2026-10-18T00:00:00Z',
      }] as any,
    } as any)

    renderPage(true)
    expect(await screen.findByText(/Jam tidak sesuai form kertas/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /unggah & submit/i })).toBeInTheDocument()
  })

  it('shows an empty state when there are no timesheets', async () => {
    renderPage(true)
    expect(await screen.findByText(/Belum Ada Timesheet/i)).toBeInTheDocument()
  })
})
