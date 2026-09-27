import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminBookingsPage } from './pages/AdminBookingsPage'
import { bookingService } from './services/bookingService'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import { ToastProvider } from '@/app/ToastContext'
import type { Booking } from '@/types/booking'

vi.mock('./services/bookingService', () => ({
  bookingService: {
    getBookings: vi.fn(),
    approveBooking: vi.fn(),
    rejectBooking: vi.fn(),
    assignUnits: vi.fn(),
  },
}))

vi.mock('@/features/equipment/services/equipmentService', () => ({
  equipmentService: {
    getUnits: vi.fn(),
  },
}))

const mockBooking: Booking = {
  id: 1,
  booking_code: 'RFA-BKG-20260925-0005',
  user_id: 7,
  project_location_id: 1,
  status: 'PENDING_APPROVAL',
  rejection_reason: null,
  total_amount: 19200000,
  project_location: {
    id: 1,
    user_id: 7,
    project_name: 'Flyover Cisauk',
    address: 'Jl. Lapan',
    city: 'Tangerang',
    pic_name: 'Budi',
    pic_phone: '081',
    latitude: null,
    longitude: null,
    is_active: true,
  } as any,
  details: [
    {
      id: 1,
      booking_id: 1,
      equipment_model_id: 3,
      quantity: 1,
      start_date: '2026-10-05',
      end_date: '2026-10-12',
      is_all_in: false,
      rental_rate_snapshot: 240000,
      subtotal: 15360000,
      created_at: '2026-09-25T10:00:00Z',
      model: { id: 3, brand: 'Komatsu', model_name: 'PC200-8', capacity_value: 20, capacity_unit: 'Ton' } as any,
    },
  ],
  created_at: '2026-09-25T10:00:00Z',
  updated_at: '2026-09-25T10:00:00Z',
}

const renderComponent = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminBookingsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('Admin Booking Queue UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(bookingService.getBookings).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [mockBooking],
      meta: { current_page: 1, per_page: 30, total: 1, last_page: 1 },
    } as any)
  })

  it('renders booking queue with approve/reject actions', async () => {
    renderComponent()

    expect(await screen.findByText('RFA-BKG-20260925-0005')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /setujui/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /tolak/i })).toBeInTheDocument()
  })

  it('approves booking via service', async () => {
    vi.mocked(bookingService.approveBooking).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...mockBooking, status: 'APPROVED' },
    } as any)

    renderComponent()

    await screen.findByText('RFA-BKG-20260925-0005')
    fireEvent.click(screen.getByRole('button', { name: /setujui/i }))

    await waitFor(() => {
      expect(bookingService.approveBooking).toHaveBeenCalledWith(1)
    })
  })

  it('requires reason before rejecting', async () => {
    vi.mocked(bookingService.rejectBooking).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...mockBooking, status: 'REJECTED' },
    } as any)

    renderComponent()

    await screen.findByText('RFA-BKG-20260925-0005')
    fireEvent.click(screen.getByRole('button', { name: /tolak/i }))

    // Short reason -> reject blocked
    fireEvent.change(screen.getByLabelText(/alasan penolakan/i), { target: { value: 'tes' } })
    fireEvent.click(screen.getByRole('button', { name: /tolak booking/i }))

    expect(await screen.findByText(/alasan penolakan minimal 10 karakter/i)).toBeInTheDocument()
    expect(bookingService.rejectBooking).not.toHaveBeenCalled()

    // Valid reason -> service called
    fireEvent.change(screen.getByLabelText(/alasan penolakan/i), {
      target: { value: 'Ketersediaan armada tidak memadai pada periode tersebut.' },
    })
    fireEvent.click(screen.getByRole('button', { name: /tolak booking/i }))

    await waitFor(() => {
      expect(bookingService.rejectBooking).toHaveBeenCalledWith(1, expect.stringContaining('Ketersediaan armada'))
    })
  })

  it('opens assign units modal for approved booking', async () => {
    const approved = { ...mockBooking, status: 'APPROVED' as const }
    vi.mocked(bookingService.getBookings).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [approved],
      meta: { current_page: 1, per_page: 30, total: 1, last_page: 1 },
    } as any)
    vi.mocked(equipmentService.getUnits).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [{ id: 50, equipment_model_id: 3, serial_number: 'KM-001', plate_number: 'B 1 RFA', status: 'AVAILABLE' }],
      meta: { current_page: 1, per_page: 100, total: 1, last_page: 1 },
    } as any)

    renderComponent()

    await screen.findByText('RFA-BKG-20260925-0005')
    fireEvent.click(screen.getByRole('button', { name: /tugaskan unit/i }))

    expect(await screen.findByText(/tugaskan unit — rfa-bkg-20260925-0005/i)).toBeInTheDocument()
    expect(await screen.findByText('KM-001')).toBeInTheDocument()
  })
})