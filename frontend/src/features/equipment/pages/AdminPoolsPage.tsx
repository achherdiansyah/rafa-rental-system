import React, { useCallback, useEffect, useState } from 'react'
import { Plus, Edit2, Trash2, MapPin } from 'lucide-react'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import type { EquipmentPool, EquipmentPoolPayload } from '@/types/equipment'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Modal } from '@/components/ui/Modal'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useToast } from '@/hooks/useToast'

const emptyForm: EquipmentPoolPayload = {
  name: '',
  address: '',
  city: '',
  latitude: 0,
  longitude: 0,
  is_active: true,
}

export const AdminPoolsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [pools, setPools] = useState<EquipmentPool[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editing, setEditing] = useState<EquipmentPool | null>(null)
  const [form, setForm] = useState<EquipmentPoolPayload>(emptyForm)
  const [isSaving, setIsSaving] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<EquipmentPool | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const load = useCallback(async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await equipmentService.getPools({ per_page: 100 })
      setPools(res.data ?? [])
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat pool armada.')
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const openCreate = () => {
    setEditing(null)
    setForm(emptyForm)
    setIsModalOpen(true)
  }

  const openEdit = (pool: EquipmentPool) => {
    setEditing(pool)
    setForm({
      name: pool.name,
      address: pool.address,
      city: pool.city ?? '',
      latitude: pool.latitude ?? 0,
      longitude: pool.longitude ?? 0,
      is_active: pool.is_active,
    })
    setIsModalOpen(true)
  }

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSaving(true)
    try {
      if (editing) {
        await equipmentService.updatePool(editing.id, form)
        showSuccessToast('Pool armada berhasil diperbarui.')
      } else {
        await equipmentService.createPool(form)
        showSuccessToast('Pool armada berhasil ditambahkan.')
      }
      setIsModalOpen(false)
      load()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menyimpan pool armada.')
    } finally {
      setIsSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await equipmentService.deletePool(deleteTarget.id)
      showSuccessToast('Pool armada berhasil dihapus.')
      load()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menghapus pool armada.')
    } finally {
      setIsDeleting(false)
      setDeleteTarget(null)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Pool Armada</h2>
          <p className="text-sm text-slate-500 mt-1">
            Lokasi asal alat berat. Jarak rute dari pool ke lokasi proyek dipakai untuk menghitung MOB/DEMOB.
          </p>
        </div>
        <Button onClick={openCreate} leftIcon={<Plus size={16} />}>Tambah Pool</Button>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Pool">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-3">{[1, 2].map((i) => <Card key={i} className="p-5"><Skeleton className="h-6 w-52" /></Card>)}</div>
      ) : pools.length === 0 ? (
        <EmptyState
          icon={<MapPin className="w-12 h-12" />}
          title="Belum Ada Pool"
          description="Tambahkan lokasi asal armada beserta koordinat latitude/longitude."
        />
      ) : (
        <div className="space-y-3">
          {pools.map((pool) => (
            <Card key={pool.id} className="p-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="space-y-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-semibold text-slate-900">{pool.name}</span>
                    <Badge variant={pool.is_active ? 'success' : 'secondary'} size="sm">
                      {pool.is_active ? 'Aktif' : 'Nonaktif'}
                    </Badge>
                  </div>
                  <p className="text-sm text-slate-600">{pool.address}{pool.city ? `, ${pool.city}` : ''}</p>
                  <p className="text-xs font-mono text-slate-400">
                    {pool.latitude ?? '-'}, {pool.longitude ?? '-'}
                  </p>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <Button variant="ghost" size="sm" onClick={() => openEdit(pool)} title="Edit pool">
                    <Edit2 size={14} />
                  </Button>
                  <Button variant="ghost" size="sm" className="text-rose-600" onClick={() => setDeleteTarget(pool)} title="Hapus pool">
                    <Trash2 size={14} />
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal
        isOpen={isModalOpen}
        onClose={() => !isSaving && setIsModalOpen(false)}
        title={editing ? 'Edit Pool Armada' : 'Tambah Pool Armada'}
        size="md"
      >
        <form onSubmit={handleSave} className="space-y-4">
          <Input
            label="Nama Pool *"
            value={form.name}
            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
            required
          />
          <Input
            label="Alamat *"
            value={form.address}
            onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
            required
          />
          <Input
            label="Kota"
            value={form.city ?? ''}
            onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))}
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Latitude *"
              type="number"
              step="0.00000001"
              value={form.latitude}
              onChange={(e) => setForm((f) => ({ ...f, latitude: Number(e.target.value) }))}
              required
            />
            <Input
              label="Longitude *"
              type="number"
              step="0.00000001"
              value={form.longitude}
              onChange={(e) => setForm((f) => ({ ...f, longitude: Number(e.target.value) }))}
              required
            />
          </div>
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={form.is_active ?? true}
              onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
            />
            Aktif
          </label>
          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <Button variant="outline" type="button" onClick={() => setIsModalOpen(false)} disabled={isSaving}>
              Batal
            </Button>
            <Button type="submit" isLoading={isSaving}>{editing ? 'Simpan Perubahan' : 'Tambah Pool'}</Button>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        isOpen={deleteTarget !== null}
        onClose={() => !isDeleting && setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Hapus Pool Armada"
        message={`Hapus pool ${deleteTarget?.name ?? ''}? Pool yang masih dipakai model/unit tidak dapat dihapus.`}
        confirmText="Hapus"
        cancelText="Batal"
        variant="danger"
        isLoading={isDeleting}
      />
    </div>
  )
}

export default AdminPoolsPage