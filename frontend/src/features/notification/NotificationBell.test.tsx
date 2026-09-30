import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { NotificationBell } from './NotificationBell'
import { notificationService } from './services/notificationService'
import type { InAppNotification } from '@/types/notification'

vi.mock('./services/notificationService', () => ({
  notificationService: { unreadCount: vi.fn(), getNotifications: vi.fn(), markRead: vi.fn(), markAllRead: vi.fn() },
}))

const unread: InAppNotification = {
  id: 'n1',
  type: 'PAYMENT',
  event: 'PAYMENT_SUBMITTED',
  entity_type: 'App\\Models\\Payment',
  entity_id: 9,
  message: 'Pembayaran perlu verifikasi',
  link: '/admin/payments',
  read_at: null,
  created_at: new Date().toISOString(),
}

const renderBell = () =>
  render(
    <MemoryRouter>
      <NotificationBell />
    </MemoryRouter>
  )

describe('Admin NotificationBell', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(notificationService.unreadCount).mockResolvedValue(2)
    vi.mocked(notificationService.getNotifications).mockResolvedValue({
      success: true,
      message: 'ok',
      data: [unread],
      meta: { total: 1, current_page: 1, per_page: 6, last_page: 1 } as never,
    } as never)
    vi.mocked(notificationService.markRead).mockResolvedValue({ ...unread, read_at: new Date().toISOString() } as never)
    vi.mocked(notificationService.markAllRead).mockResolvedValue(undefined as never)
  })

  it('shows unread count badge and hides it when zero', async () => {
    renderBell()
    expect(await screen.findByText('2')).toBeInTheDocument()

    vi.mocked(notificationService.unreadCount).mockResolvedValue(0)
    fireEvent(window, new CustomEvent('rafa:notifications-changed'))
    await waitFor(() => expect(screen.queryByText('2')).not.toBeInTheDocument())
  })

  it('opens dropdown, lists notifications and navigates + marks as read on click', async () => {
    renderBell()
    await screen.findByText('2')

    fireEvent.click(screen.getByRole('button', { name: /notifikasi/i }))
    expect(await screen.findByText('Pembayaran perlu verifikasi')).toBeInTheDocument()

    fireEvent.click(screen.getByText('Pembayaran perlu verifikasi'))
    await waitFor(() => expect(notificationService.markRead).toHaveBeenCalledWith('n1'))
  })

  it('mark all read calls endpoint and refreshes', async () => {
    renderBell()
    await screen.findByText('2')

    fireEvent.click(screen.getByRole('button', { name: /notifikasi/i }))
    fireEvent.click(screen.getByText('Tandai dibaca'))
    await waitFor(() => expect(notificationService.markAllRead).toHaveBeenCalledTimes(1))
  })

  it('shows an empty state when there are no notifications', async () => {
    vi.mocked(notificationService.unreadCount).mockResolvedValue(0)
    vi.mocked(notificationService.getNotifications).mockResolvedValue({
      success: true,
      message: 'ok',
      data: [],
      meta: { total: 0, current_page: 1, per_page: 6, last_page: 1 } as never,
    } as never)

    renderBell()
    await waitFor(() => expect(screen.queryByText('2')).not.toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: /notifikasi/i }))
    expect(await screen.findByText(/belum ada notifikasi/i)).toBeInTheDocument()
  })
})