import React, { useState, useEffect, useCallback } from 'react'
import { Plus, Search, Edit2, Trash2, Truck, Activity } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Textarea } from '@/components/form/Textarea'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/data-display/Table'
import { Pagination } from '@/components/data-display/Pagination'
import { TableSkeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { useToast } from '@/hooks/useToast'
import { useDebounce } from '@/hooks/useDebounce'
import { equipmentService } from '../services/equipmentService'
import type { EquipmentUnit, EquipmentModel, EquipmentStatus } from '@/types/equipment'
import type { ApiError, PaginationMeta } from '@/types/api'

export const AdminEquipmentUnitsPage: React.FC = () => {
  const { success, error: toastError } = useToast()

  const [units, setUnits] = useState<EquipmentUnit[]>([])
  const [modelsList, setModelsList] = useState<EquipmentModel[]>([])
  const [meta, setMeta] = useState<PaginationMeta>({ current_page: 1, per_page: 10, total: 0, last_page: 1 })
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounce(search, 300)
  const [filterModel, setFilterModel] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  // --- Form Modal State ---
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingUnit, setEditingUnit] = useState<EquipmentUnit | null>(null)
  const [modelId, setModelId] = useState<number | string>('')
  const [serialNumber, setSerialNumber] = useState('')
  const [plateNumber, setPlateNumber] = useState('')
  const [hourMeter, setHourMeter] = useState('0')
  const [yearOfMake, setYearOfMake] = useState('')
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})
  const [isSaving, setIsSaving] = useState(false)

  // --- Status Change Modal State ---
  const [statusModalUnit, setStatusModalUnit] = useState<EquipmentUnit | null>(null)
  const [targetStatus, setTargetStatus] = useState<EquipmentStatus>('AVAILABLE')
  const [statusNotes, setStatusNotes] = useState('')
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false)

  // --- Delete Dialog ---
  const [deleteTarget, setDeleteTarget] = useState<EquipmentUnit | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const fetchModelsList = useCallback(async () => {
    try {
      const res = await equipmentService.getModels({ per_page: 100 })
      setModelsList(res.data || [])
    } catch {
      // Ignore
    }
  }, [])

  const fetchUnits = useCallback(async (page: number = 1) => {
    setIsLoading(true)
    try {
      const res = await equipmentService.getUnits({
        page,
        per_page: 10,
        search: debouncedSearch || undefined,
        equipment_model_id: filterModel ? Number(filterModel) : undefined,
        status: filterStatus || undefined,
      })
      setUnits(res.data || [])
      if (res.meta) setMeta(res.meta)
    } catch {
      toastError('Gagal memuat daftar unit fisik armada.')
    } finally {
      setIsLoading(false)
    }
  }, [debouncedSearch, filterModel, filterStatus, toastError])

  useEffect(() => {
    fetchModelsList()
  }, [fetchModelsList])

  useEffect(() => {
    fetchUnits(1)
  }, [fetchUnits])

  // --- Open Create / Edit ---
  const handleOpenCreate = () => {
    setEditingUnit(null)
    setModelId(modelsList[0]?.id || '')
    setSerialNumber('')
    setPlateNumber('')
    setHourMeter('0')
    setYearOfMake(String(new Date().getFullYear()))
    setFormErrors({})
    setIsModalOpen(true)
  }

  const handleOpenEdit = (unit: EquipmentUnit) => {
    setEditingUnit(unit)
    setModelId(unit.equipment_model_id)
    setSerialNumber(unit.serial_number)
    setPlateNumber(unit.plate_number || '')
    setHourMeter(String(unit.last_hour_meter))
    setYearOfMake(unit.year_of_make ? String(unit.year_of_make) : '')
    setFormErrors({})
    setIsModalOpen(true)
  }

  const handleSaveUnit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSaving(true)
    setFormErrors({})

    const payload = {
      equipment_model_id: Number(modelId),
      serial_number: serialNumber,
      plate_number: plateNumber || undefined,
      last_hour_meter: parseFloat(hourMeter) || 0,
      year_of_make: yearOfMake ? parseInt(yearOfMake, 10) : undefined,
    }

    try {
      if (editingUnit) {
        await equipmentService.updateUnit(editingUnit.id, payload)
        success(`Data unit ${serialNumber} berhasil diperbarui.`)
      } else {
        await equipmentService.createUnit(payload)
        success(`Unit fisik baru ${serialNumber} berhasil didaftarkan.`)
      }
      setIsModalOpen(false)
      fetchUnits(meta.current_page)
    } catch (err) {
      const apiErr = err as ApiError
      if (apiErr.status === 422) {
        setFormErrors(apiErr.errors)
      } else {
        toastError(apiErr.message || 'Gagal menyimpan unit fisik.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  // --- Open Status Change ---
  const handleOpenStatusModal = (unit: EquipmentUnit) => {
    setStatusModalUnit(unit)
    setTargetStatus(unit.status === 'MAINTENANCE' ? 'AVAILABLE' : 'MAINTENANCE')
    setStatusNotes('')
  }

  const handleSaveStatus = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!statusModalUnit) return

    setIsUpdatingStatus(true)
    try {
      await equipmentService.updateUnitStatus(statusModalUnit.id, {
        status: targetStatus,
        notes: statusNotes,
      })
      success(`Status unit ${statusModalUnit.serial_number} berhasil diubah menjadi ${targetStatus}.`)
      setStatusModalUnit(null)
      fetchUnits(meta.current_page)
    } catch (err) {
      const apiErr = err as ApiError
      toastError(apiErr.message || 'Gagal memperbarui status unit.')
    } finally {
      setIsUpdatingStatus(false)
    }
  }

  // --- Delete Unit ---
  const handleConfirmDelete = async () => {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await equipmentService.deleteUnit(deleteTarget.id)
      success(`Unit fisik "${deleteTarget.serial_number}" berhasil dihapus.`)
      setDeleteTarget(null)
      fetchUnits(1)
    } catch (err) {
      const apiErr = err as ApiError
      toastError(apiErr.message || 'Gagal menghapus unit fisik.')
    } finally {
      setIsDeleting(false)
    }
  }

  const getStatusBadge = (status: EquipmentStatus) => {
    switch (status) {
      case 'AVAILABLE':
        return <Badge variant="success">AVAILABLE</Badge>
      case 'ASSIGNED':
        return <Badge variant="default">ASSIGNED</Badge>
      case 'MOBILIZING':
      case 'DEMOBILIZING':
        return <Badge variant="outline">LOGISTICS ({status})</Badge>
      case 'ON_SITE':
        return <Badge variant="secondary">ON SITE</Badge>
      case 'RETURN_INSPECTION':
        return <Badge variant="warning">INSPECTION</Badge>
      case 'MAINTENANCE':
        return <Badge variant="danger">MAINTENANCE</Badge>
      case 'DECOMMISSIONED':
        return <Badge variant="secondary">DECOMMISSIONED</Badge>
      default:
        return <Badge>{status}</Badge>
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Inventaris Unit Fisik</h2>
          <p className="text-sm text-slate-500">Pantau nomor seri mesin, plat nomor, jam kerja (HM), dan status operasional armada</p>
        </div>

        <Button onClick={handleOpenCreate} leftIcon={<Plus size={16} />}>
          Daftarkan Unit Baru
        </Button>
      </div>

      {/* Filter Toolbar */}
      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
          <Input
            placeholder="Cari nomor seri atau plat nomor lambung..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-9"
          />
        </div>

        <div className="flex gap-2">
          <Select
            value={filterModel}
            onChange={(e) => setFilterModel(e.target.value)}
            className="w-48"
          >
            <option value="">Semua Model</option>
            {modelsList.map((m) => (
              <option key={m.id} value={m.id}>{m.brand} {m.model_name}</option>
            ))}
          </Select>

          <Select
            value={filterStatus}
            onChange={(e) => setFilterStatus(e.target.value)}
            className="w-44"
          >
            <option value="">Semua Status</option>
            <option value="AVAILABLE">AVAILABLE (Tersedia)</option>
            <option value="ASSIGNED">ASSIGNED (Dialokasikan)</option>
            <option value="MOBILIZING">MOBILIZING (Pengiriman)</option>
            <option value="ON_SITE">ON_SITE (Di Proyek)</option>
            <option value="DEMOBILIZING">DEMOBILIZING (Penarikan)</option>
            <option value="RETURN_INSPECTION">INSPECTION (Pemeriksaan)</option>
            <option value="MAINTENANCE">MAINTENANCE (Bengkel)</option>
            <option value="DECOMMISSIONED">DECOMMISSIONED (Pensiun)</option>
          </Select>
        </div>
      </div>

      {/* Table Content */}
      {isLoading ? (
        <TableSkeleton rows={5} cols={6} />
      ) : units.length === 0 ? (
        <EmptyState
          icon={<Truck size={24} />}
          title="Tidak Ada Unit Fisik"
          description="Belum ada unit mesin fisik yang sesuai dengan kriteria filter Anda."
          action={
            <Button size="sm" onClick={handleOpenCreate} leftIcon={<Plus size={14} />}>
              Daftarkan Unit Pertama
            </Button>
          }
        />
      ) : (
        <>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nomor Seri & Plat</TableHead>
                <TableHead>Seri Model & Tipe</TableHead>
                <TableHead>Status Operasional</TableHead>
                <TableHead>Hour Meter (HM)</TableHead>
                <TableHead>Tahun Buat</TableHead>
                <TableHead className="text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {units.map((unit) => (
                <TableRow key={unit.id}>
                  <TableCell>
                    <div className="flex flex-col">
                      <span className="font-semibold text-slate-900">{unit.serial_number}</span>
                      <span className="text-xs text-slate-500 font-mono">{unit.plate_number || 'Tanpa Plat'}</span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-col">
                      <span className="text-sm font-medium text-slate-800">
                        {unit.model?.brand} {unit.model?.model_name}
                      </span>
                      <span className="text-xs text-slate-400">
                        {unit.model?.type?.name || '-'} ({unit.model?.capacity_value} {unit.model?.capacity_unit})
                      </span>
                    </div>
                  </TableCell>
                  <TableCell>
                    {getStatusBadge(unit.status)}
                  </TableCell>
                  <TableCell className="font-mono text-sm text-slate-700 font-medium">
                    {unit.last_hour_meter.toLocaleString('id-ID', { minimumFractionDigits: 2 })} Jam
                  </TableCell>
                  <TableCell className="text-sm text-slate-600">
                    {unit.year_of_make || '-'}
                  </TableCell>
                  <TableCell className="text-right space-x-1">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleOpenStatusModal(unit)}
                      title="Ubah Status / Servis"
                      aria-label={`Ubah status ${unit.serial_number}`}
                    >
                      <Activity size={14} />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleOpenEdit(unit)}
                      title="Edit Data Unit"
                      aria-label={`Edit ${unit.serial_number}`}
                    >
                      <Edit2 size={14} />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-rose-600 hover:bg-rose-50"
                      onClick={() => setDeleteTarget(unit)}
                      title="Hapus Unit"
                      aria-label={`Hapus ${unit.serial_number}`}
                    >
                      <Trash2 size={14} />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <Pagination
            currentPage={meta.current_page}
            totalPages={meta.last_page}
            onPageChange={(p) => fetchUnits(p)}
          />
        </>
      )}

      {/* MODAL: CREATE / EDIT UNIT */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingUnit ? 'Edit Data Unit Fisik' : 'Daftarkan Unit Fisik Baru'}
      >
        <form onSubmit={handleSaveUnit} className="space-y-4">
          <Select
            label="Pilih Seri Model Alat"
            value={modelId}
            onChange={(e) => setModelId(e.target.value)}
            error={formErrors.equipment_model_id?.[0]}
            required
          >
            {modelsList.map((m) => (
              <option key={m.id} value={m.id}>
                {m.brand} {m.model_name} - {m.type?.name} ({m.capacity_value} {m.capacity_unit})
              </option>
            ))}
          </Select>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Nomor Seri Mesin / Rangka"
              value={serialNumber}
              onChange={(e) => setSerialNumber(e.target.value)}
              error={formErrors.serial_number?.[0]}
              placeholder="Contoh: KM-PC200-001"
              required
            />

            <Input
              label="Nomor Plat / No Lambung"
              value={plateNumber}
              onChange={(e) => setPlateNumber(e.target.value)}
              error={formErrors.plate_number?.[0]}
              placeholder="Contoh: B 9101 RFA"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Hour Meter Awal (Jam)"
              type="number"
              step="0.01"
              value={hourMeter}
              onChange={(e) => setHourMeter(e.target.value)}
              error={formErrors.last_hour_meter?.[0]}
              placeholder="0.00"
              required
            />

            <Input
              label="Tahun Pembuatan"
              type="number"
              value={yearOfMake}
              onChange={(e) => setYearOfMake(e.target.value)}
              error={formErrors.year_of_make?.[0]}
              placeholder="Contoh: 2022"
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button variant="outline" type="button" onClick={() => setIsModalOpen(false)}>
              Batal
            </Button>
            <Button type="submit" isLoading={isSaving}>
              {editingUnit ? 'Simpan Perubahan' : 'Daftarkan Unit'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* MODAL: UPDATE STATUS */}
      <Modal
        isOpen={!!statusModalUnit}
        onClose={() => setStatusModalUnit(null)}
        title={`Ubah Status: ${statusModalUnit?.serial_number}`}
        size="sm"
      >
        <form onSubmit={handleSaveStatus} className="space-y-4">
          <div>
            <p className="text-xs text-slate-500 mb-2">
              Status Saat Ini:{' '}
              <span className="font-semibold text-slate-900">{statusModalUnit?.status}</span>
            </p>
            <Select
              label="Pilih Status Tujuan"
              value={targetStatus}
              onChange={(e) => setTargetStatus(e.target.value as EquipmentStatus)}
              required
            >
              <option value="AVAILABLE">AVAILABLE (Siap Operasi di Pool)</option>
              <option value="MAINTENANCE">MAINTENANCE (Masuk Bengkel / Servis)</option>
              <option value="DECOMMISSIONED">DECOMMISSIONED (Pensiun / Ditarik)</option>
            </Select>
          </div>

          <Textarea
            label="Catatan Pemeliharaan / Alasan"
            value={statusNotes}
            onChange={(e) => setStatusNotes(e.target.value)}
            placeholder="Masukkan alasan perbaikan atau catatan mekanik..."
            rows={3}
          />

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button variant="outline" type="button" onClick={() => setStatusModalUnit(null)}>
              Batal
            </Button>
            <Button type="submit" isLoading={isUpdatingStatus}>
              Perbarui Status
            </Button>
          </div>
        </form>
      </Modal>

      {/* CONFIRM DIALOG: DELETE */}
      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleConfirmDelete}
        title="Konfirmasi Hapus Unit Fisik"
        message={`Apakah Anda yakin ingin menghapus unit "${deleteTarget?.serial_number}"? Unit yang sedang tersewa tidak dapat dihapus.`}
        confirmText="Hapus Unit"
        variant="danger"
        isLoading={isDeleting}
      />
    </div>
  )
}
export default AdminEquipmentUnitsPage
