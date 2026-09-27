import React, { useState, useEffect } from 'react'
import { RadioTower } from 'lucide-react'
import { notificationService } from '@/features/notification/services/notificationService'
import type { NotificationDelivery } from '@/types/notification'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger'> = {
  SENT: 'success', FAILED: 'danger', SKIPPED: 'secondary', QUEUED: 'warning',
}

export const AdminNotificationsPage: React.FC = () => {
  const [items, setItems] = useState<NotificationDelivery[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  useEffect(() => {
    const load = async () => {
      try {
        const res = await notificationService.getDeliveries({ per_page: 50 })
        setItems(res.data ?? [])
      } catch (err: any) {
        setApiError(err?.message || 'Gagal memuat log delivery.')
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Monitor Notifikasi</h2>
        <p className="text-sm text-slate-500 mt-1">Log delivery channel (WhatsApp) — SENT / FAILED / SKIPPED untuk audit.</p>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Data">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">{[1, 2, 3].map((i) => (
          <Card key={i} className="p-5 space-y-3"><Skeleton className="h-5 w-56" /><Skeleton className="h-12 w-full rounded-xl" /></Card>
        ))}</div>
      ) : items.length > 0 ? (
        <div className="space-y-2">
          {items.map((d) => (
            <Card key={d.id} className="p-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div className="flex items-center gap-2 flex-wrap">
                  <Badge variant={STATUS_VARIANT[d.status] ?? 'secondary'} size="sm">{d.status}</Badge>
                  <span className="font-mono text-xs text-slate-900">{d.event}</span>
                  <span className="text-xs text-slate-400">{d.channel}</span>
                  {d.provider && <span className="text-xs text-slate-400">via {d.provider}</span>}
                </div>
                <div className="text-xs text-slate-500">
                  {d.recipient_phone ?? 'tanpa nomor'} • {new Date(d.created_at).toLocaleString('id-ID')}
                </div>
              </div>
              {d.error && <p className="text-xs text-rose-600 mt-1.5">{d.error}</p>}
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState icon={<RadioTower className="w-12 h-12" />} title="Belum Ada Delivery" description="Log pengiriman notifikasi akan tampil di sini." />
      )}
    </div>
  )
}
export default AdminNotificationsPage