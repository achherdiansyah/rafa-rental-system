import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { UserOutstandingPage } from '@/features/refund/pages/UserOutstandingPage'
import { AdminOutstandingPage } from '@/features/refund/pages/AdminOutstandingPage'
import { UserNotificationsPage } from '@/features/notification/pages/UserNotificationsPage'
import { AdminNotificationsPage } from '@/features/notification/pages/AdminNotificationsPage'
import { financeService } from '@/features/refund/services/refundService'
import { notificationService } from '@/features/notification/services/notificationService'
import { ToastProvider } from '@/app/ToastContext'
import type { CustomerOutstanding } from '@/types/refund'
import type { InAppNotification, NotificationDelivery } from '@/types/notification'

vi.mock('@/features/refund/services/refundService', () => ({
  refundService: {},
  financeService: { myOutstanding: vi.fn(), allOutstanding: vi.fn() },
}))

vi.mock('@/features/notification/services/notificationService', () => ({
  notificationService: {
    getNotifications: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
    getDeliveries: vi.fn(),
  },
}))

const outstanding: CustomerOutstanding = {
  user_id: 7,
  customer_name: 'PT Mitra Sejahtera',
  total_outstanding: 600000,
  open_invoice_count: 2,
  overdue_invoice_count: 1,
  eligible: false,
  invoices: [
    { id: 1, invoice_number: 'INV/202609/0001', status: 'UNPAID', grand_total: 400000, paid_amount: 0, balance_amount: 400000 },
    { id: 2, invoice_number: 'INV/202609/0002', status: 'OVERDUE', grand_total: 200000, paid_amount: 0, balance_amount: 200000 },
  ],
}

const notif: InAppNotification = {
  id: 'n1',
  type: 'App\\Notifications\\SystemNotification',
  event: 'PAYMENT_APPROVED',
  entity_type: 'App\\Models\\Payment',
  entity_id: 5,
  message: 'Pembayaran Anda terverifikasi.',
  link: null,
  read_at: null,
  created_at: '2026-09-27T10:00:00Z',
}

const delivery: NotificationDelivery = {
  id: 9,
  event: 'PAYMENT_APPROVED',
  channel: 'whatsapp',
  recipient_id: 7,
  recipient_phone: '081234567890',
  provider: null,
  status: 'SKIPPED',
  error: 'WhatsApp provider belum dikonfigurasi; pengiriman dilewati.',
  sent_at: null,
  created_at: '2026-09-27T10:00:00Z',
}

const toastRender = (page: React.ReactElement) =>
  render(<MemoryRouter><ToastProvider>{page}</ToastProvider></MemoryRouter>)

describe('Outstanding & Notification UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(financeService.myOutstanding).mockResolvedValue(outstanding as any)
    vi.mocked(financeService.allOutstanding).mockResolvedValue([outstanding] as any)
    vi.mocked(notificationService.getNotifications).mockResolvedValue({
      data: [notif],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any)
    vi.mocked(notificationService.unreadCount).mockResolvedValue(1)
    vi.mocked(notificationService.getDeliveries).mockResolvedValue({
      data: [delivery],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any)
  })

  it('renders user outstanding totals and invoice balances', async () => {
    toastRender(<UserOutstandingPage />)
    expect((await screen.findAllByText(/600\.000,00/)).length).toBeGreaterThan(0)
    expect(screen.getByText('INV/202609/0001')).toBeInTheDocument()
    expect(screen.getByText(/Saldo.*400\.000,00/)).toBeInTheDocument()
  })

  it('admin outstanding lists eligible status and expands invoices', async () => {
    toastRender(<AdminOutstandingPage />)
    expect(await screen.findByText('PT Mitra Sejahtera')).toBeInTheDocument()
    expect(screen.getByText('Outstanding')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /pt mitra sejahtera/i }))
    expect(await screen.findByText('INV/202609/0002')).toBeInTheDocument()
  })

  it('notification center shows unread count, marks read and all', async () => {
    vi.mocked(notificationService.markRead).mockResolvedValue({ ...notif, read_at: '2026-09-27T11:00:00Z' } as any)
    vi.mocked(notificationService.getNotifications).mockResolvedValueOnce({
      data: [{ ...notif, read_at: null }],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any).mockResolvedValueOnce({
      data: [{ ...notif, read_at: '2026-09-27T11:00:00Z' }],
      meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 },
    } as any)

    toastRender(<UserNotificationsPage />)
    expect(await screen.findByText('1 notifikasi belum dibaca.')).toBeInTheDocument()
    expect(screen.getByText(/Pembayaran Anda terverifikasi/i)).toBeInTheDocument()

    fireEvent.click(screen.getByText(/Pembayaran Anda terverifikasi/i))
    await waitFor(() => {
      expect(notificationService.markRead).toHaveBeenCalledWith('n1')
    })

    fireEvent.click(screen.getByRole('button', { name: /tandai semua dibaca/i }))
    await waitFor(() => {
      expect(notificationService.markAllRead).toHaveBeenCalled()
    })
  })

  it('admin notification monitor shows delivery log status and reason', async () => {
    toastRender(<AdminNotificationsPage />)
    expect(await screen.findByText('SKIPPED')).toBeInTheDocument()
    expect(screen.getByText('PAYMENT_APPROVED')).toBeInTheDocument()
    expect(screen.getByText(/belum dikonfigurasi/i)).toBeInTheDocument()
  })
})