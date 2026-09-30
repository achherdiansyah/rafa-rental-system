import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { LayoutDashboard, Truck, Sparkles, MapPin, CalendarCheck, ChevronRight } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { bookingService } from '@/features/booking/services/bookingService'
import { rentalService } from '@/features/rental/services/rentalService'
import { invoiceService } from '@/features/invoice/services/invoiceService'
import { projectLocationService } from '@/features/project/services/projectLocationService'
import { notificationService } from '@/features/notification/services/notificationService'
import { StatusBadge } from '@/components/ui/StatusBadge'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'
import type { Booking } from '@/types/booking'
import type { Rental } from '@/types/rental'
import type { Invoice } from '@/types/invoice'
import type { ProjectLocation } from '@/types/projectLocation'
import type { InAppNotification } from '@/types/notification'

const fmtIdr = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0)

const ACTIVE_BOOKING = ['SUBMITTED', 'PENDING_APPROVAL', 'APPROVED', 'PAYMENT_PENDING', 'CONFIRMED']
const ACTIVE_INVOICE = ['UNPAID', 'PARTIALLY_PAID', 'OVERDUE']

function StatCard({ label, value, sub, tone = 'text-slate-900' }: { label: string; value: string; sub?: string; tone?: string }) {
  return (
    <div className="card-surface p-5">
      <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{label}</p>
      <p className={`text-[26px] font-bold tracking-tight mt-2 ${tone}`}>{value}</p>
      {sub && <p className="text-xs text-slate-400 mt-1.5">{sub}</p>}
    </div>
  )
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Card className="p-5 card-surface">
      <h3 className="font-semibold text-slate-900 pb-3 mb-3 border-b border-slate-100">{title}</h3>
      {children}
    </Card>
  )
}

const timeAgo = (iso: string): string => {
  const diff = Date.now() - new Date(iso).getTime()
  const d = Math.floor(diff / 86400000)
  if (d > 0) return `${d} hari lalu`
  const h = Math.floor(diff / 3600000)
  return h > 0 ? `${h} jam lalu` : 'baru saja'
}

export const UserDashboardPage: React.FC = () => {
  const { user } = useAuth()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [bookings, setBookings] = useState<Booking[]>([])
  const [rentals, setRentals] = useState<Rental[]>([])
  const [invoices, setInvoices] = useState<Invoice[]>([])
  const [locations, setLocations] = useState<ProjectLocation[]>([])
  const [notifications, setNotifications] = useState<InAppNotification[]>([])

  useEffect(() => {
    let mounted = true
    const loadAll = async () => {
      setError(null)
      try {
        const [b, r, i, l, n] = (await Promise.allSettled([
          bookingService.getBookings({ per_page: 5 }),
          rentalService.getRentals({ per_page: 5 }),
          invoiceService.getInvoices({ per_page: 6 }),
          projectLocationService.getLocations(1, 6),
          notificationService.getNotifications({ per_page: 6 }),
        ])) as PromiseSettledResult<any>[]

        if (!mounted) return
        if (b.status === 'fulfilled') setBookings(b.value.data ?? [])
        if (r.status === 'fulfilled') setRentals(r.value.data ?? [])
        if (i.status === 'fulfilled') setInvoices(i.value.data ?? [])
        if (l.status === 'fulfilled') setLocations(l.value.data ?? [])
        if (n.status === 'fulfilled') setNotifications(n.value.data ?? [])
      } catch {
        if (mounted) setError('Gagal memuat dashboard.')
      } finally {
        if (mounted) setLoading(false)
      }
    }
    loadAll()
    return () => {
      mounted = false
    }
  }, [])

  const activeBookings = bookings.filter((b) => ACTIVE_BOOKING.includes(b.status)).length
  const awaitingPayment = invoices.filter((i) => ACTIVE_INVOICE.includes(i.status)).length
  const activeRentals = rentals.filter((r) => r.status === 'ONGOING').length
  const outstanding = invoices.filter((i) => ACTIVE_INVOICE.includes(i.status)).reduce((a, i) => a + (i.balance_amount ?? 0), 0)

  return (
    <div className="space-y-6 max-w-7xl">
      {/* Header */}
      <section className="rounded-2xl border border-accent-300 bg-accent-300 p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-slate-900 tracking-tight">Selamat Datang, {user?.name || 'User'}</h2>
          <p className="text-sm text-slate-800 mt-1">Kelola kebutuhan penyewaan alat berat Anda.</p>
        </div>
        <span className="hidden sm:flex h-11 w-11 items-center justify-center rounded-lg bg-accent-500 text-slate-900 shrink-0">
          <LayoutDashboard size={20} />
        </span>
      </section>

      {error && <Alert variant="danger" title="Gagal Memuat Dashboard">{error}</Alert>}

      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="card-surface p-5 space-y-3">
              <Skeleton className="h-3 w-20" />
              <Skeleton className="h-7 w-20" />
            </div>
          ))}
        </div>
      ) : (
        <>
          {/* KPIs */}
          <section className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Booking Aktif" value={String(activeBookings)} sub={`${bookings.length} total`} />
            <StatCard label="Menunggu Pembayaran" value={String(awaitingPayment)} sub="invoice belum lunas" tone="text-primary-600" />
            <StatCard label="Rental Aktif" value={String(activeRentals)} sub={`${rentals.length} total`} tone="text-emerald-600" />
            <StatCard label="Outstanding" value={fmtIdr(outstanding)} sub={`${invoices.filter((i) => ACTIVE_INVOICE.includes(i.status)).length} invoice`} tone="text-rose-600" />
          </section>

          {/* Booking terbaru & Rental berjalan */}
          <section className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Panel title="Booking Terbaru">
              {bookings.length > 0 ? (
                <div className="divide-y divide-slate-50">
                  {bookings.slice(0, 5).map((b) => (
                    <div key={b.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                      <div className="min-w-0">
                        <p className="truncate font-medium text-slate-800">{b.project_location?.project_name || b.booking_code}</p>
                        <p className="text-xs text-slate-400">{b.booking_code}</p>
                      </div>
                      <StatusBadge status={b.status} />
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-slate-400">Belum ada booking.</p>
              )}
            </Panel>
            <Panel title="Rental Berjalan">
              {rentals.length > 0 ? (
                <div className="divide-y divide-slate-50">
                  {rentals.slice(0, 5).map((r) => (
                    <div key={r.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                      <div className="min-w-0">
                        <p className="truncate font-medium text-slate-800">{r.booking?.project_location?.project_name || `Rental #${r.id}`}</p>
                        <p className="text-xs text-slate-400">{r.booking?.booking_code}</p>
                      </div>
                      <StatusBadge status={r.status} />
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-slate-400">Belum ada rental berjalan.</p>
              )}
            </Panel>
          </section>

          {/* Tagihan & Pembayaran */}
          <section className="card-surface">
            <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
              <h3 className="font-semibold text-slate-900">Tagihan & Pembayaran</h3>
              <Link to="/app/invoices" className="text-sm font-medium text-primary-700 hover:text-primary-800 inline-flex items-center gap-1">
                Lihat Semua <ChevronRight size={14} />
              </Link>
            </div>
            {invoices.length > 0 ? (
              <div className="divide-y divide-slate-50">
                {invoices.slice(0, 6).map((inv) => (
                  <div key={inv.id} className="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                    <div className="min-w-0">
                      <p className="truncate font-medium text-slate-800">{inv.invoice_number}</p>
                      <p className="text-xs text-slate-400">{inv.invoice_type}</p>
                    </div>
                    <div className="flex items-center gap-3 shrink-0">
                      <span className="font-mono text-slate-900">{fmtIdr(inv.grand_total)}</span>
                      <span className="text-xs text-slate-400 w-24 text-right">{inv.balance_amount ? `${fmtIdr(inv.balance_amount)} sisa` : ''}</span>
                      <StatusBadge status={inv.status} />
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="px-5 py-6 text-sm text-slate-400">Belum ada tagihan.</p>
            )}
          </section>

          {/* Lokasi Proyek & Notifikasi */}
          <section className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Panel title="Lokasi Proyek">
              {locations.length > 0 ? (
                <div className="divide-y divide-slate-50">
                  {locations.slice(0, 5).map((loc) => (
                    <div key={loc.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                      <div className="min-w-0">
                        <p className="truncate font-medium text-slate-800">{loc.project_name}</p>
                        <p className="text-xs text-slate-400">{loc.city}</p>
                      </div>
                      <MapPin size={15} className="text-slate-300 shrink-0" />
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-slate-400">Belum ada lokasi proyek.</p>
              )}
            </Panel>
            <Panel title="Aktivitas & Notifikasi">
              {notifications.length > 0 ? (
                <div className="divide-y divide-slate-50">
                  {notifications.slice(0, 5).map((n) => (
                    <div key={n.id} className="flex items-center gap-2 py-2.5 text-sm">
                      <span className={`mt-1.5 w-1.5 h-1.5 rounded-full shrink-0 ${n.read_at ? 'bg-transparent' : 'bg-primary-500'}`} />
                      <div className="min-w-0">
                        <p className={`truncate ${n.read_at ? 'text-slate-600' : 'font-medium text-slate-800'}`}>{n.message || n.event}</p>
                        <p className="text-xs text-slate-400">{timeAgo(n.created_at)}</p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-slate-400">Belum ada notifikasi.</p>
              )}
            </Panel>
          </section>

          {/* Quick actions */}
          <section className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Link to="/app/equipment">
              <Button variant="outline" size="lg" className="w-full justify-start"><Truck size={16} /> Cari Equipment</Button>
            </Link>
            <Link to="/app/recommendations">
              <Button variant="outline" size="lg" className="w-full justify-start"><Sparkles size={16} /> Rekomendasi Alat</Button>
            </Link>
            <Link to="/app/locations">
              <Button variant="outline" size="lg" className="w-full justify-start"><MapPin size={16} /> Tambah Lokasi</Button>
            </Link>
            <Link to="/app/bookings">
              <Button variant="outline" size="lg" className="w-full justify-start"><CalendarCheck size={16} /> Lihat Booking</Button>
            </Link>
          </section>
        </>
      )}
    </div>
  )
}
export default UserDashboardPage