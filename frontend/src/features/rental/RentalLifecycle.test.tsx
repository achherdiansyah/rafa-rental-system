import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminRentalsPage } from './pages/AdminRentalsPage'
import { rentalService } from './services/rentalService'
import { ToastProvider } from '@/app/ToastContext'
import type { Rental } from '@/types/rental'

vi.mock('./services/rentalService', () => ({
  rentalService: {
    getRentals: vi.fn(),
    getRental: vi.fn(),
    createFromBooking: vi.fn(),
    transition: vi.fn(),
    markReady: vi.fn(),
  },
}))

const mockRental: Rental = {
  id: 1,
  booking_id: 10,
  status: 'ASSIGNED',
  started_at: null,
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
  details: [{ id: 1, rental_id: 1, assignment_id: 5, status: 'ASSIGNED', check_in_hm: null, check_out_hm: null, condition_notes: null, inspection_result: null, checked_out_at: null, unit: { id: 55, serial_number: 'KM-001', plate_number: 'B 1 RFA', status: 'ASSIGNED' } }],
  created_at: '2026-09-27T10:00:00Z',
  updated_at: '2026-09-27T10:00:00Z',
}

const renderComponent = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminRentalsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Admin Rental Lifecycle UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(rentalService.getRentals).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [mockRental],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    } as any)
  })

  it('renders rentals with status and dispatch action', async () => {
    renderComponent()

    expect(await screen.findByText('RFA-BKG-20260927-0001')).toBeInTheDocument()
    expect(screen.getByText('ASSIGNED')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /kirim unit \(dispatch\)/i })).toBeInTheDocument()
  })

  it('dispatches rental after confirmation', async () => {
    vi.mocked(rentalService.transition).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...mockRental, status: 'DISPATCHED' },
    } as any)

    renderComponent()

    const dispatchBtn = await screen.findByRole('button', { name: /kirim unit \(dispatch\)/i })
    fireEvent.click(dispatchBtn)

    expect(screen.getByText(/unit fisik akan dikirim ke lokasi proyek/i)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /konfirmasi/i }))

    await waitFor(() => {
      expect(rentalService.transition).toHaveBeenCalledWith(1, 'dispatch')
    })
  })

  it('shows arrival action for dispatched rental', async () => {
    vi.mocked(rentalService.getRentals).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [{ ...mockRental, status: 'DISPATCHED' }],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    } as any)

    renderComponent()

    expect(await screen.findByRole('button', { name: /konfirmasi tiba \(arrival\)/i })).toBeInTheDocument()
  })

  it('shows ongoing action for arrived rental', async () => {
    vi.mocked(rentalService.getRentals).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [{ ...mockRental, status: 'ARRIVED' }],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    } as any)

    renderComponent()

    expect(await screen.findByRole('button', { name: /konfirmasi mulai \(ongoing\)/i })).toBeInTheDocument()
  })

  it('records inspection result via mark ready', async () => {
    vi.mocked(rentalService.getRentals).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [
        {
          ...mockRental,
          status: 'RETURN_INSPECTED',
          details: [
            { ...mockRental.details[0], status: 'RETURN_INSPECTED', inspection_result: null, checked_out_at: null },
          ],
        },
      ],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    } as any)
    vi.mocked(rentalService.markReady).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...mockRental, status: 'COMPLETED' },
    } as any)

    renderComponent()

    const inspectBtn = await screen.findByRole('button', { name: /isi hasil inspeksi/i })
    fireEvent.click(inspectBtn)

    expect(screen.getByText(/hasil inspeksi kondisi unit/i)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /butuh maintenance/i }))
    fireEvent.click(screen.getByRole('button', { name: /simpan hasil inspeksi/i }))

    await waitFor(() => {
      expect(rentalService.markReady).toHaveBeenCalledWith(1, 'MAINTENANCE', undefined)
    })
  })

  it('shows empty state and handles error', async () => {
    vi.mocked(rentalService.getRentals).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 50, total: 0, last_page: 1 },
    } as any)

    const { unmount } = renderComponent()
    expect(await screen.findByText(/belum ada rental berjalan/i)).toBeInTheDocument()
    unmount()

    vi.mocked(rentalService.getRentals).mockRejectedValueOnce({ message: 'Server gagal.' })
    renderComponent()
    expect(await screen.findByText(/gagal memuat rental/i)).toBeInTheDocument()
  })
})