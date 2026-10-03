import React, { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Bell, ChevronRight } from 'lucide-react'
import { cn } from '@/utils/cn'
import { useAuth } from '@/hooks/useAuth'
import { notificationService } from './services/notificationService'
import type { InAppNotification } from '@/types/notification'
import { labelFor, targetFor } from './utils'

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1) return 'baru saja'
  if (m < 60) return `${m} mnt`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h} jam`
  return `${Math.floor(h / 24)} hari`
}

export const NotificationBell: React.FC = () => {
  const navigate = useNavigate()
  const { user } = useAuth()
  const role = user?.role ?? 'USER'
  const base = role === 'ADMIN' || role === 'OWNER' ? '/admin' : '/app'
  const [open, setOpen] = useState(false)
  const [count, setCount] = useState(0)
  const [items, setItems] = useState<InAppNotification[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const rootRef = useRef<HTMLDivElement>(null)

  const loadCount = async () => {
    try {
      const countRes = await notificationService.unreadCount()
      setCount(countRes)
    } catch {
      /* ignore */
    }
  }

  const loadList = async () => {
    setLoading(true)
    setError(null)
    try {
      const countRes = await Promise.all([
        notificationService.unreadCount(),
        notificationService.getNotifications({ per_page: 6 }),
      ])
      setCount(countRes[0])
      setItems(countRes[1].data ?? [])
    } catch {
      setError('Gagal memuat notifikasi.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    // Only fetch the unread badge count on mount; lazily fetch the full list
    // when the dropdown is opened (avoids a parallel burst).
    loadCount()
    const onChanged = () => loadCount()
    window.addEventListener('rafa:notifications-changed', onChanged)
    return () => {
      window.removeEventListener('rafa:notifications-changed', onChanged)
    }
  }, [])

  // Open handler: lazy-load the notification list only when user clicks the bell
  const toggle = () => {
    setOpen((o) => {
      if (!o && items.length === 0) {
        loadList()
      }
      return !o
    })
  }

  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false)
    const onPointer = (e: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false)
    }
    window.addEventListener('keydown', onKey)
    document.addEventListener('mousedown', onPointer)
    return () => {
      window.removeEventListener('keydown', onKey)
      document.removeEventListener('mousedown', onPointer)
    }
  }, [open])

  const openItem = async (item: InAppNotification) => {
    setOpen(false)
    navigate(targetFor(item, role))
    if (!item.read_at) {
      try {
        await notificationService.markRead(item.id)
        window.dispatchEvent(new CustomEvent('rafa:notifications-changed'))
      } catch {
        /* ignore */
      }
    }
  }

  const markAll = async () => {
    try {
      await notificationService.markAllRead()
      window.dispatchEvent(new CustomEvent('rafa:notifications-changed'))
    } catch {
      /* ignore */
    }
  }

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        onClick={toggle}
        aria-label={open ? 'Tutup notifikasi' : `Notifikasi, ${count} belum dibaca`}
        aria-expanded={open}
        className="relative p-2 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-50 transition-colors cursor-pointer"
      >
        <Bell size={19} />
        {count > 0 && (
          <span className="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[17px] h-[17px] px-1 rounded-full text-[10px] font-bold text-white bg-rose-500">
            {count > 9 ? '9+' : count}
          </span>
        )}
      </button>

      {open && (
        <div
          role="menu"
          aria-label="Notifikasi"
          className="absolute right-0 top-11 w-80 sm:w-96 rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden z-50"
        >
          <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100">
            <p className="text-sm font-semibold text-slate-900">Notifikasi</p>
            <button
              type="button"
              onClick={markAll}
              className="text-xs text-primary-600 hover:text-primary-700 font-medium px-2 py-1 rounded-md hover:bg-primary-50 cursor-pointer"
            >
              Tandai dibaca
            </button>
          </div>

          <div className="max-h-80 overflow-y-auto divide-y divide-slate-50">
            {loading ? (
              <div className="px-4 py-8 text-center text-sm text-slate-400">Memuat…</div>
            ) : error ? (
              <div className="px-4 py-8 text-center text-sm text-rose-500">{error}</div>
            ) : items.length === 0 ? (
              <div className="px-4 py-10 text-center space-y-2">
                <Bell size={22} className="mx-auto text-slate-300" />
                <p className="text-sm text-slate-400">Belum ada notifikasi.</p>
              </div>
            ) : (
              items.map((item) => {
                const unread = !item.read_at
                return (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => openItem(item)}
                    className="w-full text-left px-4 py-3 hover:bg-slate-50 transition-colors cursor-pointer"
                  >
                    <div className="flex items-start gap-2.5">
                      <span
                        className={cn(
                          'mt-1.5 w-2 h-2 rounded-full shrink-0',
                          unread ? 'bg-primary-500' : 'bg-transparent'
                        )}
                      />
                      <div className="min-w-0 flex-1">
                        <p className={cn('text-sm truncate', unread ? 'font-semibold text-slate-800' : 'text-slate-600')}>
                          {labelFor(item)}
                        </p>
                        <p className="text-xs text-slate-400 mt-0.5">{timeAgo(item.created_at)}</p>
                      </div>
                      <ChevronRight size={14} className="text-slate-300 mt-1 shrink-0" />
                    </div>
                  </button>
                )
              })
            )}
          </div>

          <button
            type="button"
            onClick={() => {
              setOpen(false)
              navigate(`${base}/notifications`)
            }}
            className="w-full flex items-center justify-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 px-4 py-3 border-t border-slate-100 hover:bg-primary-50/40 cursor-pointer"
          >
            Lihat semua <ChevronRight size={14} />
          </button>
        </div>
      )}
    </div>
  )
}
export default NotificationBell