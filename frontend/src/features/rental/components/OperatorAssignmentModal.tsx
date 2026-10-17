import React, { useEffect, useState } from 'react'
import { Truck, UserPlus, RefreshCw, UserMinus } from 'lucide-react'
import type { Rental } from '@/types/rental'
import { operatorAssignmentService } from '../services/operatorAssignmentService'
import type { OperatorOption, RentalOperatorAssignment } from '../services/operatorAssignmentService'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Select } from '@/components/form/Select'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

interface Props {
  rental: Rental | null
  isOpen: boolean
  onClose: () => void
}

export const OperatorAssignmentModal: React.FC<Props> = ({ rental, isOpen, onClose }) => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()
  const [operators, setOperators] = useState<OperatorOption[]>([])
  const [assignments, setAssignments] = useState<RentalOperatorAssignment[]>([])
  const [drafts, setDrafts] = useState<Record<number, string>>({})
  const [busy, setBusy] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = async (rentalId: number) => {
    setError(null)
    try {
      const [ops, asg] = await Promise.all([
        operatorAssignmentService.getOperators(),
        operatorAssignmentService.getAssignments(rentalId),
      ])
      setOperators(ops.data ?? [])
      setAssignments(asg.data ?? [])
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat data penugasan Operator.')
    }
  }

  useEffect(() => {
    if (isOpen && rental) {
      setDrafts({})
      load(rental.id)
    }
  }, [isOpen, rental])

  if (!rental) return null

  const activeFor = (detailId: number) =>
    assignments.find((a) => a.rental_detail_id === detailId && a.status === 'ACTIVE')

  const handleAssign = async (detailId: number) => {
    const operatorId = Number(drafts[detailId] ?? 0)
    if (!operatorId) return
    setBusy(`assign-${detailId}`)
    try {
      const res = await operatorAssignmentService.assign(rental.id, detailId, operatorId)
      if (res.success) {
        showSuccessToast('Operator berhasil ditugaskan.')
        await load(rental.id)
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menugaskan Operator.')
    } finally {
      setBusy(null)
    }
  }

  const handleReplace = async (assignmentId: number) => {
    const operatorId = Number(drafts[assignmentId] ?? 0)
    const reason = window.prompt('Alasan penggantian Operator (wajib):')?.trim() ?? ''
    if (!operatorId || reason.length < 5) {
      if (operatorId) showErrorToast('Alasan penggantian minimal 5 karakter.')
      return
    }
    setBusy(`replace-${assignmentId}`)
    try {
      const res = await operatorAssignmentService.replace(assignmentId, operatorId, reason)
      if (res.success) {
        showSuccessToast('Operator berhasil diganti.')
        await load(rental.id)
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengganti Operator.')
    } finally {
      setBusy(null)
    }
  }

  const handleEnd = async (assignmentId: number) => {
    const reason = window.prompt('Alasan pengakhiran penugasan (wajib):')?.trim() ?? ''
    if (reason.length < 5) {
      showErrorToast('Alasan pengakhiran minimal 5 karakter.')
      return
    }
    setBusy(`end-${assignmentId}`)
    try {
      const res = await operatorAssignmentService.end(assignmentId, reason)
      if (res.success) {
        showSuccessToast('Penugasan Operator diakhiri.')
        await load(rental.id)
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengakhiri penugasan.')
    } finally {
      setBusy(null)
    }
  }

  return (
    <Modal isOpen={isOpen} onClose={() => !busy && onClose()} title={`Penugasan Operator - ${rental.booking?.booking_code ?? `Rental #${rental.id}`}`} size="lg">
      <div className="space-y-5">
        {error && <Alert variant="danger" title="Gagal Memuat">{error}</Alert>}

        {operators.length === 0 && (
          <Alert variant="warning" title="Belum Ada Operator">
            Belum ada akun dengan role OPERATOR. Tugaskan role melalui menu Verifikasi Akun.
          </Alert>
        )}

        {rental.details.map((detail) => {
          const active = activeFor(detail.id)
          return (
            <div key={detail.id} className="border border-slate-100 rounded-xl p-4 bg-slate-50/40 space-y-3">
              <div className="flex items-center gap-2 flex-wrap">
                <Truck size={16} className="text-slate-400" />
                <span className="font-mono text-sm text-slate-900">{detail.unit?.serial_number ?? `Detail #${detail.id}`}</span>
                {detail.unit?.plate_number && <span className="text-xs text-slate-500">{detail.unit.plate_number}</span>}
                {active && <Badge variant="success" size="sm">Aktif: {active.operator_name}</Badge>}
              </div>

              {active ? (
                <div className="flex flex-col sm:flex-row sm:items-end gap-2">
                  <Select
                    label="Operator Pengganti"
                    value={drafts[active.id] ?? ''}
                    onChange={(e) => setDrafts((prev) => ({ ...prev, [active.id]: e.target.value }))}
                  >
                    <option value="">-- Pilih Operator --</option>
                    {operators.map((o) => (
                      <option key={o.id} value={o.id}>{o.name}</option>
                    ))}
                  </Select>
                  <div className="flex gap-2">
                    <Button size="sm" className="gap-1.5" isLoading={busy === `replace-${active.id}`} onClick={() => handleReplace(active.id)}>
                      <RefreshCw size={14} /> Ganti
                    </Button>
                    <Button size="sm" variant="outline" className="gap-1.5 text-rose-600 hover:bg-rose-50" isLoading={busy === `end-${active.id}`} onClick={() => handleEnd(active.id)}>
                      <UserMinus size={14} /> Akhiri
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="flex flex-col sm:flex-row sm:items-end gap-2">
                  <Select
                    label="Pilih Operator"
                    value={drafts[detail.id] ?? ''}
                    onChange={(e) => setDrafts((prev) => ({ ...prev, [detail.id]: e.target.value }))}
                  >
                    <option value="">-- Pilih Operator --</option>
                    {operators.map((o) => (
                      <option key={o.id} value={o.id}>{o.name}</option>
                    ))}
                  </Select>
                  <Button size="sm" className="gap-1.5" disabled={!drafts[detail.id]} isLoading={busy === `assign-${detail.id}`} onClick={() => handleAssign(detail.id)}>
                    <UserPlus size={14} /> Tugaskan
                  </Button>
                </div>
              )}
            </div>
          )
        })}

        <div className="flex justify-end pt-2 border-t border-slate-100">
          <Button variant="outline" onClick={onClose} disabled={!!busy}>Tutup</Button>
        </div>
      </div>
    </Modal>
  )
}

export default OperatorAssignmentModal
