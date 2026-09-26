import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { UserBookingsPage } from './pages/UserBookingsPage'
import { bookingService } from './services/bookingService'
import { ToastProvider } from '@/app/ToastContext'
import type { Booking } from '@/types/booking'

vi.mock('./services/bookingService', () => ({
  bookingService: {
    createFromCart: vi.fn(),
    getBookings: vi.fn(),
    getBooking: vi.fn(),
    submitBooking: vi.fn(),
  },
}))

const mockBooking: Booking = {
  id: 1,
  booking_code: 'RFA-BKG-20260925-0010',
  user_id: 10,
  project_location_id: 1,
  status: 'DRAFT',
  rejection_reason: null,
  total_amount: 19200000,
  project_location: {
    id: 1,
    user_id: 10,
    project_name: 'Flyover Cisauk',
    address: 'Jl. Lapan Raya',
    city: 'Tangerang',
    pic_name: 'Budi',
    pic_phone: '08123',
    latitude: null,
    longitude: null,
    is_active: true,
  } as any,
  details: [
    {
      id: 1,
      booking_id: 1,
      equipment_model_id: 5,
      quantity: 2,
      start_date: '2026-10-05',
      end_date: '2026-10-12',
      is_all_in: false,
      rental_rate_snapshot: 250000,
      subtotal: 19200000,
      created_at: '2026-09-25T10:00:00Z',
      model: {
        id: 5,
        brand: 'Komatsu',
        model_name: 'PC200-8',
        capacity_value: 20,
        capacity_unit: 'Ton',
      } as any,
    },
  ],
  created_at: '2026-09-25T10:00:00Z',
  updated_at: '2026-09-25T10:00:00Z',
}

const renderComponent = () => {
  return render(
    <MemoryRouter>
      <ToastProvider>
        <UserBookingsPage />
      </ToastProvider>
    </MemoryRouter>
  )
}

describe('Booking UI Suite', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(bookingService.getBookings).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [mockBooking],
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)
  })

  it('lists booking cards with code, status, and details', async () => {
    renderComponent()

    expect(screen.getByRole('heading', { name: /sewa saya/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText('RFA-BKG-20260925-0010')).toBeInTheDocument()
      expect(screen.getByText('DRAFT')).toBeInTheDocument()
      expect(screen.getByText('Komatsu PC200-8')).toBeInTheDocument()
      expect(screen.getByText('Flyover Cisauk')).toBeInTheDocument()
    })
  })

  it('submits a draft booking after confirmation', async () => {
    vi.mocked(bookingService.submitBooking).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...mockBooking, status: 'PENDING_APPROVAL' },
    } as any)

    renderComponent()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /ajukan ke approval/i })).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /ajukan ke approval/i }))

    expect(screen.getByText(/booking RFA-BKG-20260925-0010 akan diajukan/i)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: /ajukan sekarang/i }))

    await waitFor(() => {
      expect(bookingService.submitBooking).toHaveBeenCalledWith(1)
    })
  })

  it('shows empty state when there are no bookings', async () => {
    vi.mocked(bookingService.getBookings).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
    } as any)

    renderComponent()

    await waitFor(() => {
      expect(screen.getByText(/belum ada pengajuan sewa/i)).toBeInTheDocument()
    })
  })

  it('handles API error when loading bookings', async () => {
    vi.mocked(bookingService.getBookings).mockRejectedValueOnce({
      message: 'Server tidak merespons.',
    })

    renderComponent()

    await waitFor(() => {
      expect(screen.getByText(/gagal memuat booking/i)).toBeInTheDocument()
    })
  })
})