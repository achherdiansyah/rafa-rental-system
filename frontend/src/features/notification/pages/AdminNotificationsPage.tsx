import React, { useState, useEffect } from 'react'
import { Bell, CheckCheck, ChevronRight } from 'lucide-react'
import { notificationService } from '@/features/notification/services/notificationService'
import type { InAppNotification } from '@/types/notification'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'
import { labelFor, targetFor } from '../utils'
import { useNavigate } from 'react-router-dom'

const ACTION_LABEL: Record<string, string> = {
  BOOKING_SUBMITTED: 'Buka Approval Booking',
  PAYMENT_SUBMITTED: 'Verifikasi Pembayaran',
  PAYMENT_APPROVED: 'Lihat Invoice',
  TIMESHEET_SUBMITTED: 'Validasi Timesheet',
  REFUND_PENDING: 'Proses Refund',
  INVOICE_OVERDUE: 'Lihat Tagihan',
  OUTSTANDING_REMINDER: 'Lihat Outstanding',
}

const actionLabelFor = (item: InAppNotification): string =>
  (item.event && ACTION_LABEL[item.event]) || 'Buka Halaman'

export const AdminNotificationsPage: React.FC = () => {
  const navigate = useNavigate()
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [items, setItems] = useState<InAppNotification[]>([])
  const [unread, setUnread] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [apiError, setApiError] = useState<string | null>(null)

  const load = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const [list, count] = await Promise.all([
        notificationService.getNotifications({ per_page: 50 }),
        notificationService.unreadCount(),
      ])
      setItems(list.data ?? [])
      setUnread(count)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat notifikasi.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    load()
    const onChanged = () => load()
    window.addEventListener('rafa:notifications-changed', onChanged)
    window.addEventListener('focus', load)
    return () => {
      window.removeEventListener('rafa:notifications-changed', onChanged)
      window.removeEventListener('focus', load)
    }
  }, [])

  const markRead = async (id: string) => {
    try {
      await notificationService.markRead(id)
    } catch {
      /* ignore */
    }
    window.dispatchEvent(new CustomEvent('rafa:notifications-changed'))
    const [list, count] = await Promise.all([
      notificationService.getNotifications({ per_page: 50 }),
      notificationService.unreadCount(),
    ])
    setItems(list.data ?? [])
    setUnread(count)
  }

  const markAll = async () => {
    setBusy(true)
    try {
      await notificationService.markAllRead()
      showSuccessToast('Semua notifikasi ditandai sudah dibaca.')
      window.dispatchEvent(new CustomEvent('rafa:notifications-changed'))
      await load()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menandai notifikasi.')
    } finally {
      setBusy(false)
    }
  }

  const handleNotificationClick = async (n: InAppNotification) => {
    if (!n.read_at) {
      await markRead(n.id)
    }
    navigate(targetFor(n, 'ADMIN'))
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <Bell size={20} /> Pusat Notifikasi Admin
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            {unread} notifikasi belum dibaca. Klik untuk membuka halaman terkait.
          </p>
        </div>
        <Button variant="outline" size="sm" isLoading={busy} onClick={markAll}>
          <CheckCheck size={14} className="mr-1" /> Tandai Semua Dibaca
        </Button>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Notifikasi">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i} className="p-4">
              <Skeleton className="h-4 w-1/3 mb-2" />
              <Skeleton className="h-4 w-3/4" />
            </Card>
          ))}
        </div>
      ) : items.length > 0 ? (
        <div className="space-y-2">
          {items.map((n) => {
            const unreadItem = !n.read_at
            return (
              <Card
                key={n.id}
                className={`p-4 cursor-pointer hover:bg-slate-50 transition-colors ${
                  unreadItem ? 'bg-primary-50/40 border-primary-200' : 'bg-white'
                }`}
                onClick={() => handleNotificationClick(n)}
              >
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="flex items-start gap-3 min-w-0 flex-1">
                    <span
                      className={`mt-1.5 w-2 h-2 rounded-full shrink-0 ${unreadItem ? 'bg-primary-600' : 'bg-slate-200'}`}
                    />
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2 flex-wrap">
                        <p className={`text-sm truncate ${unreadItem ? 'font-semibold text-slate-900' : 'text-slate-700'}`}>
                          {labelFor(n)}
                        </p>
                        {unreadItem && <Badge variant="warning" size="sm">Belum dibaca</Badge>}
                      </div>
                      <p className="text-sm text-slate-600 mt-0.5">{n.message || n.event || '-'}</p>
                      <p className="text-xs text-slate-400 mt-1">
                        {new Date(n.created_at).toLocaleString('id-ID')}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <Badge variant="secondary" size="sm">{n.event ?? n.type}</Badge>
                    <span className="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700">
                      {actionLabelFor(n)} <ChevronRight size={13} />
                    </span>
                  </div>
                </div>
              </Card>
            )
          })}
        </div>
      ) : (
        <EmptyState
          icon={<Bell className="w-12 h-12" />}
          title="Tidak Ada Notifikasi"
          description="Notifikasi event operasional dan keuangan akan muncul di sini."
        />
      )}
    </div>
  )
}

export default AdminNotificationsPage