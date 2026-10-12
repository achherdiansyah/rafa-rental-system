import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
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

const makeRental = (id: number, bookingCode: string, status: Rental['status']): Rental => ({
  id,
  booking_id: 100 + id,
  status,
  started_at: status === 'ONGOING' ? '2026-09-27T10:00:00Z' : null,
  completed_at: null,
  booking: {
    id: 100 + id,
    booking_code: bookingCode,
    user_id: 7,
    project_location_id: id,
    status: 'CONFIRMED',
    project_location: { id: id, project_name: `Proyek ${id}`, city: 'Tangerang' } as any,
    details: [],
  } as any,
  details: [
    {
      id: id,
      rental_id: id,
      assignment_id: 500 + id,
      status,
      check_in_hm: null,
      check_out_hm: null,
      condition_notes: null,
      inspection_result: null,
      checked_out_at: null,
      unit: { id: 50 + id, serial_number: `KM-00${id}`, plate_number: `B ${id} RFA`, status },
    },
  ],
  created_at: '2026-09-27T10:00:00Z',
  updated_at: '2026-09-27T10:00:00Z',
})

const renderComponent = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminRentalsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Admin Rental Lifecycle — multiple orders', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders correct action for EACH rental based on its own status (no cross-contamination)', async () => {
    vi.mocked(rentalService.getRentals).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [
        makeRental(1, 'RFA-BKG-001', 'ASSIGNED'), // -> dispatch action
        makeRental(2, 'RFA-BKG-002', 'ARRIVED'), // -> ongoing action
        makeRental(3, 'RFA-BKG-003', 'ONGOING'), // -> return action
        makeRental(4, 'RFA-BKG-004', 'DEMOBILIZING'), // -> inspect action
      ],
      meta: { current_page: 1, per_page: 50, total: 4, last_page: 1 },
    } as any)

    renderComponent()

    await waitFor(() => {
      expect(screen.getByText('RFA-BKG-001')).toBeInTheDocument()
      expect(screen.getByText('RFA-BKG-002')).toBeInTheDocument()
      expect(screen.getByText('RFA-BKG-003')).toBeInTheDocument()
      expect(screen.getByText('RFA-BKG-004')).toBeInTheDocument()
    })

    // Each rental must show the action matching ITS OWN status
    expect(screen.getByRole('button', { name: /kirim unit \(dispatch\)/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /konfirmasi mulai \(ongoing\)/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /catat pengembalian \(return\)/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /mulai inspeksi \(inspection\)/i })).toBeInTheDocument()
  })

  it('shows all status badges for every rental, including completed (no hidden status)', async () => {
    vi.mocked(rentalService.getRentals).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [makeRental(1, 'RFA-BKG-001', 'ASSIGNED'), makeRental(2, 'RFA-BKG-002', 'COMPLETED')],
      meta: { current_page: 1, per_page: 50, total: 2, last_page: 1 },
    } as any)

    renderComponent()

    await waitFor(() => {
      expect(screen.getByText('ASSIGNED')).toBeInTheDocument()
      expect(screen.getByText('COMPLETED')).toBeInTheDocument()
    })

    // COMPLETED rental: no action; ASSIGNED rental: dispatch action
    expect(screen.queryByRole('button', { name: /kirim unit \(dispatch\)/i })).toBeInTheDocument()
  })
})