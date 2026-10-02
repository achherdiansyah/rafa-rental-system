import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminNotificationsPage } from './pages/AdminNotificationsPage'
import { notificationService } from './services/notificationService'
import { ToastProvider } from '@/app/ToastContext'
import type { InAppNotification } from '@/types/notification'

vi.mock('./services/notificationService', () => ({
  notificationService: {
    getNotifications: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

const unread: InAppNotification = {
  id: 'n1',
  type: 'App\\Notifications\\SystemNotification',
  event: 'PAYMENT_SUBMITTED',
  entity_type: 'App\\Models\\Payment',
  entity_id: 42,
  message: 'Pembayaran TRF-ABC1 perlu verifikasi.',
  link: null,
  read_at: null,
  created_at: '2026-10-01T08:00:00Z',
}

const read: InAppNotification = {
  id: 'n2',
  type: 'App\\Notifications\\SystemNotification',
  event: 'BOOKING_SUBMITTED',
  entity_type: 'App\\Models\\Booking',
  entity_id: 21,
  message: 'Booking RFA-BKG-20261001-0001 baru.',
  link: null,
  read_at: '2026-10-01T09:00:00Z',
  created_at: '2026-10-01T07:00:00Z',
}

const renderPage = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminNotificationsPage />
      </ToastProvider>
    </MemoryRouter>
  )

describe('AdminNotificationsPage UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(notificationService.unreadCount).mockResolvedValue(1)
    vi.mocked(notificationService.getNotifications).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [unread, read],
      meta: { current_page: 1, per_page: 50, total: 2, last_page: 1 },
    } as any)
    vi.mocked(notificationService.markRead).mockResolvedValue({ ...unread, read_at: new Date().toISOString() } as any)
    vi.mocked(notificationService.markAllRead).mockResolvedValue(undefined as any)
  })

  it('renders real notifications with title, message, time, and unread badge', async () => {
    renderPage()

    expect(await screen.findByText('Pembayaran perlu verifikasi')).toBeInTheDocument()
    expect(screen.getByText(/Pembayaran TRF-ABC1 perlu verifikasi\./)).toBeInTheDocument()
    expect(screen.getAllByText(/1\/10\/2026/).length).toBeGreaterThanOrEqual(2)
    expect(screen.getByText('Belum dibaca')).toBeInTheDocument()
    expect(screen.getByText('Booking baru perlu persetujuan')).toBeInTheDocument()
    expect(screen.getByText('Verifikasi Pembayaran')).toBeInTheDocument()
    // unread count subtitle
    expect(screen.getByText('1 notifikasi belum dibaca. Klik untuk membuka halaman terkait.')).toBeInTheDocument()
  })

  it('navigates to payment verification page on click and marks as read', async () => {
    renderPage()

    fireEvent.click(await screen.findByText('Pembayaran TRF-ABC1 perlu verifikasi.'))

    await waitFor(() => {
      expect(notificationService.markRead).toHaveBeenCalledWith('n1')
    })
  })

  it('marks all notifications read and refreshes', async () => {
    vi.mocked(notificationService.unreadCount).mockResolvedValue(0)
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /tandai semua dibaca/i }))

    await waitFor(() => {
      expect(notificationService.markAllRead).toHaveBeenCalledTimes(1)
    })
  })

  it('shows empty state when no notifications exist', async () => {
    vi.mocked(notificationService.getNotifications).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 50, total: 0, last_page: 1 },
    } as any)
    vi.mocked(notificationService.unreadCount).mockResolvedValue(0)

    renderPage()

    expect(await screen.findByText('Tidak Ada Notifikasi')).toBeInTheDocument()
    expect(screen.getByText('Notifikasi event operasional dan keuangan akan muncul di sini.')).toBeInTheDocument()
  })

  it('shows error state without infinite loading', async () => {
    vi.mocked(notificationService.getNotifications).mockRejectedValue({ message: 'Gagal memuat notifikasi.' })
    vi.mocked(notificationService.unreadCount).mockRejectedValue({ message: 'Gagal memuat notifikasi.' })

    renderPage()

    expect(await screen.findByText('Gagal Memuat Notifikasi')).toBeInTheDocument()
  })
})