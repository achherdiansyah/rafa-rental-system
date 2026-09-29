import React, { useState, useEffect } from 'react'
import { Bell, CheckCheck } from 'lucide-react'
import { notificationService } from '@/features/notification/services/notificationService'
import type { InAppNotification } from '@/types/notification'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { useToast } from '@/hooks/useToast'

export const UserNotificationsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()
  const [items, setItems] = useState<InAppNotification[]>([])
  const [unread, setUnread] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [busy, setBusy] = useState(false)

  const load = async () => {
    setIsLoading(true)
    try {
      const [list, count] = await Promise.all([
        notificationService.getNotifications({ per_page: 50 }),
        notificationService.unreadCount(),
      ])
      setItems(list.data ?? [])
      setUnread(count)
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memuat notifikasi.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const markRead = async (id: string) => {
    await notificationService.markRead(id)
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

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <Bell size={20} /> Pusat Notifikasi
          </h2>
          <p className="text-sm text-slate-500 mt-1">{unread} notifikasi belum dibaca.</p>
        </div>
        <Button variant="outline" size="sm" isLoading={busy} onClick={markAll}>
          <CheckCheck size={14} className="mr-1" /> Tandai Semua Dibaca
        </Button>
      </div>

      {isLoading ? (
        <div className="space-y-3">{Array.from({ length: 4 }).map((_, i) => (
          <Card key={i} className="p-4"><Skeleton className="h-4 w-3/4" /></Card>
        ))}</div>
      ) : items.length > 0 ? (
        <div className="space-y-2">
          {items.map((n) => (
            <Card key={n.id} className={`p-4 cursor-pointer ${n.read_at ? 'bg-white' : 'bg-primary-50/40 border-primary-200'}`} onClick={() => !n.read_at && markRead(n.id)}>
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm text-slate-800">{n.message}</p>
                  <p className="text-xs text-slate-400 mt-0.5">
                    {n.event ?? n.type} • {new Date(n.created_at).toLocaleString('id-ID')}
                  </p>
                </div>
                {!n.read_at && <span className="w-2 h-2 rounded-full bg-primary-600 mt-1.5 shrink-0" aria-label="belum dibaca" />}
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState icon={<Bell className="w-12 h-12" />} title="Tidak Ada Notifikasi" description="Notifikasi event penting akan muncul di sini." />
      )}
    </div>
  )
}
export default UserNotificationsPage