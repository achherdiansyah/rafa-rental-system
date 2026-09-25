import React, { useState, useEffect, useCallback } from 'react'
import { Plus, Edit2, History, DollarSign } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/data-display/Table'
import { Pagination } from '@/components/data-display/Pagination'
import { TableSkeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { useToast } from '@/hooks/useToast'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import type { EquipmentPrice, EquipmentModel } from '@/types/equipment'
import type { ApiError, PaginationMeta } from '@/types/api'

export const OwnerPricingPage: React.FC = () => {
  const { success, error: toastError } = useToast()

  const [prices, setPrices] = useState<EquipmentPrice[]>([])
  const [modelsList, setModelsList] = useState<EquipmentModel[]>([])
  const [meta, setMeta] = useState<PaginationMeta>({ current_page: 1, per_page: 10, total: 0, last_page: 1 })
  const [filterModel, setFilterModel] = useState('')
  const [filterScheme, setFilterScheme] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  // --- Form Modal State ---
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingPrice, setEditingPrice] = useState<EquipmentPrice | null>(null)
  const [modelId, setModelId] = useState<number | string>('')
  const [priceType, setPriceType] = useState<'HOURLY' | 'DAILY' | 'MONTHLY' | 'LUMP_SUM'>('HOURLY')
  const [isAllIn, setIsAllIn] = useState(false)
  const [baseRate, setBaseRate] = useState('')
  const [minimumHours, setMinimumHours] = useState('8')
  const [overtimeRate, setOvertimeRate] = useState('0')
  const [effectiveDate, setEffectiveDate] = useState(new Date().toISOString().split('T')[0])
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})
  const [isSaving, setIsSaving] = useState(false)

  // --- Version History Modal State ---
  const [historyPrice, setHistoryPrice] = useState<EquipmentPrice | null>(null)

  const fetchModelsList = useCallback(async () => {
    try {
      const res = await equipmentService.getModels({ per_page: 100 })
      setModelsList(res.data || [])
    } catch {
      // Ignore
    }
  }, [])

  const fetchPrices = useCallback(async (page: number = 1) => {
    setIsLoading(true)
    try {
      const res = await equipmentService.getPrices({
        page,
        per_page: 10,
        equipment_model_id: filterModel ? Number(filterModel) : undefined,
        is_all_in: filterScheme === '' ? undefined : filterScheme === 'all_in',
      })
      setPrices(res.data || [])
      if (res.meta) setMeta(res.meta)
    } catch {
      toastError('Gagal memuat master tarif harga.')
    } finally {
      setIsLoading(false)
    }
  }, [filterModel, filterScheme, toastError])

  useEffect(() => {
    fetchModelsList()
  }, [fetchModelsList])

  useEffect(() => {
    fetchPrices(1)
  }, [fetchPrices])

  const handleOpenCreate = () => {
    setEditingPrice(null)
    setModelId(modelsList[0]?.id || '')
    setPriceType('HOURLY')
    setIsAllIn(false)
    setBaseRate('')
    setMinimumHours('8')
    setOvertimeRate('0')
    setEffectiveDate(new Date().toISOString().split('T')[0])
    setFormErrors({})
    setIsModalOpen(true)
  }

  const handleOpenEdit = (price: EquipmentPrice) => {
    setEditingPrice(price)
    setModelId(price.equipment_model_id)
    setPriceType(price.price_type)
    setIsAllIn(price.is_all_in)
    setBaseRate(String(price.base_rate))
    setMinimumHours(String(price.minimum_hours))
    setOvertimeRate(String(price.overtime_rate))
    setEffectiveDate(price.effective_date)
    setFormErrors({})
    setIsModalOpen(true)
  }

  const handleSavePrice = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSaving(true)
    setFormErrors({})

    const payload = {
      equipment_model_id: Number(modelId),
      price_type: priceType,
      is_all_in: isAllIn,
      base_rate: parseFloat(baseRate) || 0,
      minimum_hours: parseInt(minimumHours, 10) || 0,
      overtime_rate: parseFloat(overtimeRate) || 0,
      effective_date: effectiveDate,
    }

    try {
      if (editingPrice) {
        await equipmentService.updatePrice(editingPrice.id, {
          base_rate: payload.base_rate,
          minimum_hours: payload.minimum_hours,
          overtime_rate: payload.overtime_rate,
          effective_date: payload.effective_date,
        })
        success('Tarif harga berhasil diperbarui dan versi baru tercatat.')
      } else {
        await equipmentService.createPrice(payload)
        success('Master tarif harga baru berhasil ditetapkan.')
      }
      setIsModalOpen(false)
      fetchPrices(meta.current_page)
    } catch (err) {
      const apiErr = err as ApiError
      if (apiErr.status === 422) {
        setFormErrors(apiErr.errors)
      } else {
        toastError(apiErr.message || 'Gagal menyimpan tarif harga.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Master Tarif & Versi Harga</h2>
          <p className="text-sm text-slate-500">Otoritas penentuan tarif sewa dasar (All-in / Non All-in) dan audit versi harga</p>
        </div>

        <Button onClick={handleOpenCreate} leftIcon={<Plus size={16} />}>
          Tetapkan Tarif Baru
        </Button>
      </div>

      {/* Filter Toolbar */}
      <div className="flex flex-col sm:flex-row gap-3">
        <Select
          value={filterModel}
          onChange={(e) => setFilterModel(e.target.value)}
          className="w-56"
        >
          <option value="">Semua Model Alat</option>
          {modelsList.map((m) => (
            <option key={m.id} value={m.id}>{m.brand} {m.model_name}</option>
          ))}
        </Select>

        <Select
          value={filterScheme}
          onChange={(e) => setFilterScheme(e.target.value)}
          className="w-48"
        >
          <option value="">Semua Skema</option>
          <option value="all_in">All-in (Unit + BBM + Operator)</option>
          <option value="non_all_in">Non All-in (Bare Rental)</option>
        </Select>
      </div>

      {/* Table Content */}
      {isLoading ? (
        <TableSkeleton rows={4} cols={6} />
      ) : prices.length === 0 ? (
        <EmptyState
          icon={<DollarSign size={24} />}
          title="Belum Ada Master Tarif"
          description="Belum ada skema harga sewa yang ditetapkan untuk kriteria filter ini."
          action={
            <Button size="sm" onClick={handleOpenCreate} leftIcon={<Plus size={14} />}>
              Tetapkan Tarif Sekarang
            </Button>
          }
        />
      ) : (
        <>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Seri Model Armada</TableHead>
                <TableHead>Skema Sewa</TableHead>
                <TableHead>Tarif Dasar / Jam</TableHead>
                <TableHead>Min. Jam</TableHead>
                <TableHead>Tarif Overtime / Jam</TableHead>
                <TableHead>Mulai Berlaku</TableHead>
                <TableHead className="text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {prices.map((price) => (
                <TableRow key={price.id}>
                  <TableCell className="font-semibold text-slate-900">
                    {price.model ? `${price.model.brand} ${price.model.model_name}` : `Model #${price.equipment_model_id}`}
                  </TableCell>
                  <TableCell>
                    <Badge variant={price.is_all_in ? 'default' : 'secondary'}>
                      {price.is_all_in ? 'All-in' : 'Non All-in'}
                    </Badge>
                  </TableCell>
                  <TableCell className="font-mono text-sm font-bold text-slate-900">
                    Rp {price.base_rate.toLocaleString('id-ID')}
                  </TableCell>
                  <TableCell className="text-sm text-slate-600">
                    {price.minimum_hours} Jam/hari
                  </TableCell>
                  <TableCell className="font-mono text-sm text-slate-600">
                    Rp {price.overtime_rate.toLocaleString('id-ID')}
                  </TableCell>
                  <TableCell className="text-sm text-slate-500">
                    {price.effective_date}
                  </TableCell>
                  <TableCell className="text-right space-x-1">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => setHistoryPrice(price)}
                      title="Riwayat Versi Harga"
                      aria-label={`Riwayat versi ${price.model?.brand || ''} ${price.model?.model_name || price.equipment_model_id}`}
                    >
                      <History size={14} />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleOpenEdit(price)}
                      title="Perbarui Tarif"
                      aria-label={`Edit tarif ${price.model?.model_name}`}
                    >
                      <Edit2 size={14} />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <Pagination
            currentPage={meta.current_page}
            totalPages={meta.last_page}
            onPageChange={(p) => fetchPrices(p)}
          />
        </>
      )}

      {/* MODAL: CREATE / EDIT PRICE */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingPrice ? 'Perbarui Tarif Master (Buat Versi Baru)' : 'Tetapkan Tarif Master Baru'}
      >
        <form onSubmit={handleSavePrice} className="space-y-4">
          {!editingPrice && (
            <>
              <Select
                label="Pilih Model Armada"
                value={modelId}
                onChange={(e) => setModelId(e.target.value)}
                error={formErrors.equipment_model_id?.[0]}
                required
              >
                {modelsList.map((m) => (
                  <option key={m.id} value={m.id}>{m.brand} {m.model_name}</option>
                ))}
              </Select>

              <Select
                label="Skema Paket Sewa"
                value={isAllIn ? '1' : '0'}
                onChange={(e) => setIsAllIn(e.target.value === '1')}
                required
              >
                <option value="0">Non All-in (Bare Rental - Unit Saja)</option>
                <option value="1">All-in (Termasuk Operator + BBM + Maintenance Harian)</option>
              </Select>

              <Select
                label="Tipe Penagihan"
                value={priceType}
                onChange={(e) => setPriceType(e.target.value as 'HOURLY' | 'DAILY' | 'MONTHLY' | 'LUMP_SUM')}
                required
              >
                <option value="HOURLY">Per Jam (HOURLY)</option>
                <option value="DAILY">Per Hari (DAILY)</option>
                <option value="MONTHLY">Per Bulan (MONTHLY)</option>
                <option value="LUMP_SUM">Paket Flat (LUMP_SUM)</option>
              </Select>
            </>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Tarif Dasar / Jam (Rp)"
              type="number"
              step="1000"
              value={baseRate}
              onChange={(e) => setBaseRate(e.target.value)}
              error={formErrors.base_rate?.[0]}
              placeholder="Contoh: 250000"
              required
            />

            <Input
              label="Batas Minimum Jam / Hari"
              type="number"
              value={minimumHours}
              onChange={(e) => setMinimumHours(e.target.value)}
              error={formErrors.minimum_hours?.[0]}
              placeholder="Default: 8"
              required
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Tarif Overtime / Jam (Rp)"
              type="number"
              step="1000"
              value={overtimeRate}
              onChange={(e) => setOvertimeRate(e.target.value)}
              error={formErrors.overtime_rate?.[0]}
              placeholder="Contoh: 300000"
              required
            />

            <Input
              label="Tanggal Mulai Berlaku"
              type="date"
              value={effectiveDate}
              onChange={(e) => setEffectiveDate(e.target.value)}
              error={formErrors.effective_date?.[0]}
              required
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button variant="outline" type="button" onClick={() => setIsModalOpen(false)}>
              Batal
            </Button>
            <Button type="submit" isLoading={isSaving}>
              {editingPrice ? 'Simpan Versi Baru' : 'Tetapkan Tarif'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* MODAL: VERSION HISTORY */}
      <Modal
        isOpen={!!historyPrice}
        onClose={() => setHistoryPrice(null)}
        title={`Riwayat Versi Harga: ${historyPrice?.model?.brand} ${historyPrice?.model?.model_name}`}
        size="lg"
      >
        <div className="space-y-4">
          <p className="text-xs text-slate-500">
            Audit Immutability: Seluruh riwayat perubahan tarif master tersimpan secara permanen untuk integritas finansial masa lalu.
          </p>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Waktu Perubahan</TableHead>
                <TableHead>Tarif Lama</TableHead>
                <TableHead>Tarif Baru</TableHead>
                <TableHead>Diubah Oleh</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {historyPrice?.versions && historyPrice.versions.length > 0 ? (
                historyPrice.versions.map((ver) => (
                  <TableRow key={ver.id}>
                    <TableCell className="text-xs text-slate-600">
                      {new Date(ver.changed_at).toLocaleString('id-ID')}
                    </TableCell>
                    <TableCell className="font-mono text-xs text-slate-500">
                      Rp {ver.old_base_rate.toLocaleString('id-ID')}
                    </TableCell>
                    <TableCell className="font-mono text-xs font-bold text-emerald-600">
                      Rp {ver.new_base_rate.toLocaleString('id-ID')}
                    </TableCell>
                    <TableCell className="text-xs text-slate-700">
                      {ver.changed_by_user?.name || `User #${ver.changed_by}`}
                    </TableCell>
                  </TableRow>
                ))
              ) : (
                <TableRow>
                  <TableCell colSpan={4} className="text-center py-4 text-xs text-slate-400">
                    Belum ada riwayat versi lanjutan.
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>
      </Modal>
    </div>
  )
}
export default OwnerPricingPage
