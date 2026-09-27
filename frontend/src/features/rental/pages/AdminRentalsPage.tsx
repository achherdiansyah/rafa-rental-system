import React, { useState, useEffect } from 'react'
import { Truck, Send, MapPin, PlayCircle, Undo2, ClipboardCheck, Wrench, AlertTriangle, CheckCircle2 } from 'lucide-react'
import { rentalService } from '../services/rentalService'
import type { Rental } from '@/types/rental'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  ASSIGNED: 'secondary',
  DISPATCHED: 'warning',
  ARRIVED: 'warning',
  ONGOING: 'success',
  DEMOBILIZING: 'warning',
  RETURN_INSPECTED: 'warning',
  COMPLETED: 'success',
  CANCELLED: 'danger',
}

type TransitionTarget = 'dispatch' | 'arrive' | 'start' | 'return' | 'inspect'
type InspectionResult = 'READY' | 'MAINTENANCE' | 'DAMAGED'

interface PendingAction {
  rental: Rental
  target: TransitionTarget
  label: string
  message: string
}

export const AdminRentalsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [rentals, setRentals] = useState<Rental[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)

  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null)
  const [isConfirmOpen, setIsConfirmOpen] = useState(false)

  const [inspectionRental, setInspectionRental] = useState<Rental | null>(null)
  const [inspectionResult, setInspectionResult] = useState<InspectionResult>('READY')
  const [inspectionNotes, setInspectionNotes] = useState('')

  const loadRentals = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await rentalService.getRentals({ per_page: 50 })
      if (res.success && res.data) {
        setRentals(res.data)
      }
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat daftar rental.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadRentals()
  }, [])

  const nextAction = (rental: Rental): { target: TransitionTarget; label: string } | null => {
    switch (rental.status) {
      case 'ASSIGNED':
        return { target: 'dispatch', label: 'Kirim Unit (Dispatch)' }
      case 'DISPATCHED':
        return { target: 'arrive', label: 'Konfirmasi Tiba (Arrival)' }
      case 'ARRIVED':
        return { target: 'start', label: 'Konfirmasi Mulai (Ongoing)' }
      case 'ONGOING':
        return { target: 'return', label: 'Catat Pengembalian (Return)' }
      case 'DEMOBILIZING':
        return { target: 'inspect', label: 'Mulai Inspeksi (Inspection)' }
      case 'RETURN_INSPECTED':
        return { target: 'inspect', label: 'Isi Hasil Inspeksi' }
      default:
        return null
    }
  }

  const openConfirm = (rental: Rental) => {
    const action = nextAction(rental)
    if (!action) return

    if (rental.status === 'RETURN_INSPECTED') {
      setInspectionRental(rental)
      setInspectionResult('READY')
      setInspectionNotes('')
      return
    }

    const messages: Record<string, string> = {
      dispatch: 'Unit fisik akan dikirim ke lokasi proyek (status unit → MOBILIZING).',
      arrive: 'Konfirmasi unit telah tiba di lokasi proyek (status unit → ON_SITE).',
      start: 'Konfirmasi pekerjaan rental dimulai secara operasional (BAST check-in, started_at dicatat).',
      return: 'Catat pengembalian unit ke pool (status rental → RETURNING, unit → DEMOBILIZING).',
      inspect: 'Unit masuk pemeriksaan kondisi (status unit → RETURN_INSPECTION). Dan belum siap dipakai kembali.',
    }

    setPendingAction({ rental, target: action.target, label: action.label, message: messages[action.target] })
    setIsConfirmOpen(true)
  }

  const handleInspect = async () => {
    if (!inspectionRental) return
    setActingId(inspectionRental.id)
    try {
      const res = await rentalService.markReady(inspectionRental.id, inspectionResult, inspectionNotes || undefined)
      if (res.success && res.data) {
        showSuccessToast(`Inspeksi #${res.data.booking?.booking_code ?? res.data.id} → ${res.data.status} (${res.data.details?.[0]?.inspection_result ?? '-'}).`)
        loadRentals()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menyimpan hasil inspeksi.')
    } finally {
      setActingId(null)
      setInspectionRental(null)
    }
  }

  const handleConfirm = async () => {
    if (!pendingAction) return
    setActingId(pendingAction.rental.id)
    try {
      const res = await rentalService.transition(pendingAction.rental.id, pendingAction.target)
      if (res.success && res.data) {
        showSuccessToast(`Rental ${res.data.booking?.booking_code ?? ''} → ${res.data.status}.`)
        loadRentals()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menjalankan transisi rental.')
    } finally {
      setActingId(null)
      setIsConfirmOpen(false)
      setPendingAction(null)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Ekskusi Rental</h2>
        <p className="text-sm text-slate-500 mt-1">
          Dispatch, konfirmasi kedatangan, pengembalian unit, dan inspeksi kondisi sebelum kembali siap dipakai.
        </p>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Rental">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <Card key={i} className="p-5 space-y-4">
              <Skeleton className="h-6 w-48" />
              <Skeleton className="h-16 w-full rounded-xl" />
            </Card>
          ))}
        </div>
      ) : rentals.length > 0 ? (
        <div className="space-y-4">
          {rentals.map((rental) => {
            const action = nextAction(rental)
            return (
              <Card key={rental.id} className="p-5">
                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                  <div className="space-y-2">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-mono text-sm font-bold text-slate-900">
                        {rental.booking?.booking_code ?? `Rental #${rental.id}`}
                      </span>
                      <Badge variant={STATUS_VARIANT[rental.status] ?? 'secondary'} size="sm">
                        {rental.status}
                      </Badge>
                    </div>

                    {rental.booking?.project_location && (
                      <div className="flex items-start gap-2 text-sm text-slate-600">
                        <MapPin size={14} className="text-primary-600 shrink-0 mt-0.5" />
                        <span>
                          <strong className="text-slate-900">{rental.booking.project_location.project_name}</strong>
                          <span className="block text-xs text-slate-500">
                            {rental.booking.project_location.city}
                          </span>
                        </span>
                      </div>
                    )}

                    <div className="text-xs text-slate-500">
                      Dibuat {new Date(rental.created_at).toLocaleDateString('id-ID', { dateStyle: 'medium' })}
                      {rental.started_at ? ` • Mulai ${new Date(rental.started_at).toLocaleString('id-ID')}` : ''}
                    </div>

                    {/* Unit list */}
                    <div className="flex flex-wrap gap-2 mt-1">
                      {rental.details.map((d) => (
                        <span key={d.id} className="text-xs bg-slate-50 border border-slate-100 rounded-lg px-2 py-1">
                          <Truck size={11} className="inline mr-1 text-slate-400" />
                          <span className="font-mono">{d.unit?.serial_number ?? '-'}</span>
                          <span className="text-slate-400 ml-1">{d.unit?.plate_number ?? ''}</span>
                          {d.inspection_result && (
                            <span
                              className={`ml-1 font-semibold ${
                                d.inspection_result === 'READY'
                                  ? 'text-emerald-600'
                                  : d.inspection_result === 'MAINTENANCE'
                                    ? 'text-amber-600'
                                    : 'text-red-600'
                              }`}
                            >
                              • {d.inspection_result}
                            </span>
                          )}
                        </span>
                      ))}
                    </div>
                  </div>

                  {action && (
                    <Button
                      variant="primary"
                      size="sm"
                      className="gap-1.5 shrink-0"
                      isLoading={actingId === rental.id}
                      onClick={() => openConfirm(rental)}
                    >
                      {action.target === 'dispatch' ? <Send size={15} /> : action.target === 'arrive' ? <MapPin size={15} /> : action.target === 'start' ? <PlayCircle size={15} /> : action.target === 'return' ? <Undo2 size={15} /> : <ClipboardCheck size={15} />}
                      {action.label}
                    </Button>
                  )}
                </div>
              </Card>
            )
          })}
        </div>
      ) : (
        <EmptyState
          icon={<Truck className="w-12 h-12" />}
          title="Belum Ada Rental Berjalan"
          description="Rental dibuat dari booking CONFIRMED yang unit fisiknya telah ditugaskan."
        />
      )}

      <ConfirmDialog
        isOpen={isConfirmOpen}
        onClose={() => !actingId && setIsConfirmOpen(false)}
        onConfirm={handleConfirm}
        title={pendingAction?.label ?? ''}
        message={pendingAction?.message ?? ''}
        confirmText="Konfirmasi"
        cancelText="Batal"
        isLoading={actingId !== null}
      />

      <ConfirmDialog
        isOpen={inspectionRental !== null}
        onClose={() => !actingId && setInspectionRental(null)}
        onConfirm={handleInspect}
        title="Hasil Inspeksi Kondisi Unit"
        message={`Tentukan kondisi unit ${inspectionRental?.booking?.booking_code ?? ''} setelah pengembalian. Unit hanya tersedia kembali bila dinyatakan READY.`}
        confirmText="Simpan Hasil Inspeksi"
        cancelText="Batal"
        isLoading={actingId !== null}
      >
        <div className="space-y-4 mt-4">
          <div className="grid grid-cols-1 gap-2">
            {(
              [
                { value: 'READY', label: 'Siap Pakai', desc: 'Unit layak digunakan kembali (AVAILABLE)', icon: CheckCircle2, tone: 'text-emerald-600' },
                { value: 'MAINTENANCE', label: 'Butuh Maintenance', desc: 'Unit masuk perawatan (MAINTENANCE)', icon: Wrench, tone: 'text-amber-600' },
                { value: 'DAMAGED', label: 'Rusak', desc: 'Unit rusak tercatat (dipindah ke MAINTENANCE, tanpa tagihan otomatis)', icon: AlertTriangle, tone: 'text-red-600' },
              ] as const
            ).map((opt) => (
              <button
                key={opt.value}
                type="button"
                onClick={() => setInspectionResult(opt.value)}
                className={`flex items-start gap-3 rounded-xl border p-3 text-left transition-colors ${
                  inspectionResult === opt.value
                    ? 'border-primary-600 bg-primary-50 ring-1 ring-primary-600'
                    : 'border-slate-200 bg-white hover:border-slate-300'
                }`}
              >
                <opt.icon size={18} className={`shrink-0 mt-0.5 ${opt.tone}`} />
                <span>
                  <span className="block text-sm font-semibold text-slate-900">
                    {opt.label} <span className="text-slate-400 font-normal">{opt.value}</span>
                  </span>
                  <span className="block text-xs text-slate-500">{opt.desc}</span>
                </span>
              </button>
            ))}
          </div>

          <textarea
            value={inspectionNotes}
            onChange={(e) => setInspectionNotes(e.target.value)}
            placeholder="Catatan kondisi fisik unit (optional)…"
            rows={3}
            className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600"
          />
        </div>
      </ConfirmDialog>
    </div>
  )
}
export default AdminRentalsPage