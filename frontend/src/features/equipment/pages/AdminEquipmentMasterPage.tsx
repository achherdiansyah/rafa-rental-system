import React, { useState, useEffect, useCallback, useRef } from 'react'
import { Plus, Search, Edit2, Trash2, Layers, Truck, Image, X } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Textarea } from '@/components/form/Textarea'
import { Switch } from '@/components/form/Switch'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Tabs } from '@/components/ui/Tabs'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/data-display/Table'
import { Pagination } from '@/components/data-display/Pagination'
import { TableSkeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { useToast } from '@/hooks/useToast'
import { useDebounce } from '@/hooks/useDebounce'
import { equipmentService } from '../services/equipmentService'
import type { EquipmentType, EquipmentModel } from '@/types/equipment'
import type { ApiError, PaginationMeta } from '@/types/api'

export const AdminEquipmentMasterPage: React.FC = () => {
  const { success, error: toastError } = useToast()

  const [activeTab, setActiveTab] = useState<'models' | 'types'>('models')

  // --- State Models ---
  const [models, setModels] = useState<EquipmentModel[]>([])
  const [modelMeta, setModelMeta] = useState<PaginationMeta>({ current_page: 1, per_page: 10, total: 0, last_page: 1 })
  const [modelSearch, setModelSearch] = useState('')
  const debouncedModelSearch = useDebounce(modelSearch, 300)
  const [filterType, setFilterType] = useState('')
  const [filterBrand, setFilterBrand] = useState('')
  const [isLoadingModels, setIsLoadingModels] = useState(true)

  // --- State Types ---
  const [types, setTypes] = useState<EquipmentType[]>([])
  const [allTypesList, setAllTypesList] = useState<EquipmentType[]>([])
  const [typeMeta, setTypeMeta] = useState<PaginationMeta>({ current_page: 1, per_page: 10, total: 0, last_page: 1 })
  const [typeSearch, setTypeSearch] = useState('')
  const debouncedTypeSearch = useDebounce(typeSearch, 300)
  const [isLoadingTypes, setIsLoadingTypes] = useState(true)

  // --- Modal States ---
  const [isTypeModalOpen, setIsTypeModalOpen] = useState(false)
  const [editingType, setEditingType] = useState<EquipmentType | null>(null)
  const [typeName, setTypeName] = useState('')
  const [typeDesc, setTypeDesc] = useState('')

  const [isModelModalOpen, setIsModelModalOpen] = useState(false)
  const [editingModel, setEditingModel] = useState<EquipmentModel | null>(null)
  const [modelTypeId, setModelTypeId] = useState<number | string>('')
  const [modelBrand, setModelBrand] = useState('')
  const [modelName, setModelName] = useState('')
  const [capacityValue, setCapacityValue] = useState('')
  const [capacityUnit, setCapacityUnit] = useState('Ton')
  const [modelIsActive, setModelIsActive] = useState(true)

  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})
  const [isSaving, setIsSaving] = useState(false)

  // --- Delete Dialog ---
  const [deleteTarget, setDeleteTarget] = useState<{ type: 'type' | 'model'; id: number; name: string } | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  // --- Photo Management Modal State ---
  const [photoModalModel, setPhotoModalModel] = useState<EquipmentModel | null>(null)
  const [isUploadingPhoto, setIsUploadingPhoto] = useState(false)
  const fileInputRef = useRef<HTMLInputElement>(null)

  // Fetch Types List (All for select options)
  const fetchAllTypes = useCallback(async () => {
    try {
      const res = await equipmentService.getTypes(undefined, true)
      setAllTypesList((res.data as unknown as EquipmentType[]) || [])
    } catch {
      // Handled
    }
  }, [])

  // Fetch Models
  const fetchModels = useCallback(async (page: number = 1) => {
    setIsLoadingModels(true)
    try {
      const res = await equipmentService.getModels({
        page,
        per_page: 10,
        search: debouncedModelSearch || undefined,
        equipment_type_id: filterType ? Number(filterType) : undefined,
        brand: filterBrand || undefined,
      })
      setModels(res.data || [])
      if (res.meta) setModelMeta(res.meta)
    } catch {
      toastError('Gagal memuat daftar model armada.')
    } finally {
      setIsLoadingModels(false)
    }
  }, [debouncedModelSearch, filterType, filterBrand, toastError])

  // Fetch Paginated Types
  const fetchTypes = useCallback(async (page: number = 1) => {
    setIsLoadingTypes(true)
    try {
      const res = await equipmentService.getTypes(debouncedTypeSearch || undefined, false, page)
      setTypes((res.data as unknown as EquipmentType[]) || [])
      if (res.meta) setTypeMeta(res.meta)
    } catch {
      toastError('Gagal memuat daftar tipe alat.')
    } finally {
      setIsLoadingTypes(false)
    }
  }, [debouncedTypeSearch, toastError])

  useEffect(() => {
    fetchAllTypes()
  }, [fetchAllTypes])

  useEffect(() => {
    if (activeTab === 'models') {
      fetchModels(1)
    } else {
      fetchTypes(1)
    }
  }, [activeTab, fetchModels, fetchTypes])

  // --- Type Actions ---
  const handleOpenCreateType = () => {
    setEditingType(null)
    setTypeName('')
    setTypeDesc('')
    setFormErrors({})
    setIsTypeModalOpen(true)
  }

  const handleOpenEditType = (type: EquipmentType) => {
    setEditingType(type)
    setTypeName(type.name)
    setTypeDesc(type.description || '')
    setFormErrors({})
    setIsTypeModalOpen(true)
  }

  const handleSaveType = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSaving(true)
    setFormErrors({})

    try {
      if (editingType) {
        await equipmentService.updateType(editingType.id, { name: typeName, description: typeDesc })
        success(`Tipe alat "${typeName}" berhasil diperbarui.`)
      } else {
        await equipmentService.createType({ name: typeName, description: typeDesc })
        success(`Tipe alat baru "${typeName}" berhasil ditambahkan.`)
      }
      setIsTypeModalOpen(false)
      fetchTypes(typeMeta.current_page)
      fetchAllTypes()
    } catch (err) {
      const apiErr = err as ApiError
      if (apiErr.status === 422) {
        setFormErrors(apiErr.errors)
      } else {
        toastError(apiErr.message || 'Gagal menyimpan tipe alat.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  // --- Model Actions ---
  const handleOpenCreateModel = () => {
    setEditingModel(null)
    setModelTypeId(allTypesList[0]?.id || '')
    setModelBrand('')
    setModelName('')
    setCapacityValue('')
    setCapacityUnit('Ton')
    setModelIsActive(true)
    setFormErrors({})
    setIsModelModalOpen(true)
  }

  const handleOpenEditModel = (model: EquipmentModel) => {
    setEditingModel(model)
    setModelTypeId(model.equipment_type_id)
    setModelBrand(model.brand)
    setModelName(model.model_name)
    setCapacityValue(String(model.capacity_value))
    setCapacityUnit(model.capacity_unit)
    setModelIsActive(model.is_active)
    setFormErrors({})
    setIsModelModalOpen(true)
  }

  const handleSaveModel = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSaving(true)
    setFormErrors({})

    const payload = {
      equipment_type_id: Number(modelTypeId),
      brand: modelBrand,
      model_name: modelName,
      capacity_value: parseFloat(capacityValue),
      capacity_unit: capacityUnit,
      is_active: modelIsActive,
    }

    try {
      if (editingModel) {
        await equipmentService.updateModel(editingModel.id, payload)
        success(`Model armada "${modelName}" berhasil diperbarui.`)
      } else {
        await equipmentService.createModel(payload)
        success(`Model armada baru "${modelName}" berhasil ditambahkan.`)
      }
      setIsModelModalOpen(false)
      fetchModels(modelMeta.current_page)
    } catch (err) {
      const apiErr = err as ApiError
      if (apiErr.status === 422) {
        setFormErrors(apiErr.errors)
      } else {
        toastError(apiErr.message || 'Gagal menyimpan model armada.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  // --- Confirm Delete Execution ---
  const handleConfirmDelete = async () => {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      if (deleteTarget.type === 'type') {
        await equipmentService.deleteType(deleteTarget.id)
        success(`Tipe alat "${deleteTarget.name}" berhasil dihapus.`)
        fetchTypes(1)
        fetchAllTypes()
      } else {
        await equipmentService.deleteModel(deleteTarget.id)
        success(`Model armada "${deleteTarget.name}" berhasil dihapus.`)
        fetchModels(1)
      }
      setDeleteTarget(null)
    } catch (err) {
      const apiErr = err as ApiError
      toastError(apiErr.message || 'Gagal menghapus data.')
    } finally {
      setIsDeleting(false)
    }
  }

  // --- Photo Upload Handler ---
  const handleUploadPhoto = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file || !photoModalModel) return

    setIsUploadingPhoto(true)
    try {
      await equipmentService.uploadModelPhoto(photoModalModel.id, file)
      success('Foto alat berat berhasil diunggah.')
      fetchModels(modelMeta.current_page)
      // Close or refresh model state
      setPhotoModalModel(null)
    } catch (err) {
      const apiErr = err as ApiError
      toastError(apiErr.message || 'Gagal mengunggah foto.')
    } finally {
      setIsUploadingPhoto(false)
      if (fileInputRef.current) fileInputRef.current.value = ''
    }
  }

  const handleDeletePhoto = async (modelId: number, attachmentId: number) => {
    try {
      await equipmentService.deleteModelPhoto(modelId, attachmentId)
      success('Foto berhasil dihapus.')
      fetchModels(modelMeta.current_page)
      setPhotoModalModel(null)
    } catch (err) {
      const apiErr = err as ApiError
      toastError(apiErr.message || 'Gagal menghapus foto.')
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Master Data Armada</h2>
          <p className="text-sm text-slate-500">Kelola spesifikasi model alat berat dan kategori peralatan</p>
        </div>

        <div className="flex gap-2">
          {activeTab === 'models' ? (
            <Button onClick={handleOpenCreateModel} leftIcon={<Plus size={16} />}>
              Tambah Model Baru
            </Button>
          ) : (
            <Button onClick={handleOpenCreateType} leftIcon={<Plus size={16} />}>
              Tambah Tipe Baru
            </Button>
          )}
        </div>
      </div>

      {/* Tabs */}
      <Tabs
        activeTab={activeTab}
        onChange={(tab) => setActiveTab(tab as 'models' | 'types')}
        tabs={[
          { id: 'models', label: 'Model Armada', count: modelMeta.total },
          { id: 'types', label: 'Kategori / Tipe Alat', count: typeMeta.total },
        ]}
      />

      {/* TAB 1: MODELS */}
      {activeTab === 'models' && (
        <div className="space-y-4">
          {/* Filter Toolbar */}
          <div className="flex flex-col sm:flex-row gap-3">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
              <Input
                placeholder="Cari model atau merk alat..."
                value={modelSearch}
                onChange={(e) => setModelSearch(e.target.value)}
                className="pl-9"
              />
            </div>

            <div className="flex gap-2">
              <Select
                value={filterType}
                onChange={(e) => setFilterType(e.target.value)}
                className="w-44"
              >
                <option value="">Semua Tipe</option>
                {allTypesList.map((t) => (
                  <option key={t.id} value={t.id}>{t.name}</option>
                ))}
              </Select>

              <Select
                value={filterBrand}
                onChange={(e) => setFilterBrand(e.target.value)}
                className="w-36"
              >
                <option value="">Semua Merk</option>
                <option value="Komatsu">Komatsu</option>
                <option value="Caterpillar">Caterpillar</option>
                <option value="Kobelco">Kobelco</option>
                <option value="Hitachi">Hitachi</option>
                <option value="Dynapac">Dynapac</option>
              </Select>
            </div>
          </div>

          {/* Table */}
          {isLoadingModels ? (
            <TableSkeleton rows={5} cols={6} />
          ) : models.length === 0 ? (
            <EmptyState
              icon={<Truck size={24} />}
              title="Belum Ada Model Armada"
              description="Tidak ditemukan model alat berat yang sesuai dengan filter pencarian Anda."
              action={
                <Button size="sm" onClick={handleOpenCreateModel} leftIcon={<Plus size={14} />}>
                  Tambah Model Sekarang
                </Button>
              }
            />
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Merk & Model</TableHead>
                    <TableHead>Kategori</TableHead>
                    <TableHead>Kapasitas</TableHead>
                    <TableHead>Unit Fisik Terdaftar</TableHead>
                    <TableHead>Status Katalog</TableHead>
                    <TableHead className="text-right">Aksi</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {models.map((model) => (
                    <TableRow key={model.id}>
                      <TableCell className="font-semibold text-slate-900">
                        {model.brand} {model.model_name}
                      </TableCell>
                      <TableCell>
                        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 bg-slate-100 px-2.5 py-1 rounded-md">
                          <Layers size={12} /> {model.type?.name || '-'}
                        </span>
                      </TableCell>
                      <TableCell>
                        {model.capacity_value} {model.capacity_unit}
                      </TableCell>
                      <TableCell>
                        <span className="text-sm font-medium text-slate-700">
                          {model.units_count ?? 0} Unit
                        </span>
                      </TableCell>
                      <TableCell>
                        <Badge variant={model.is_active ? 'success' : 'secondary'}>
                          {model.is_active ? 'Aktif' : 'Nonaktif'}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right space-x-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setPhotoModalModel(model)}
                          title="Kelola Foto Alat"
                          aria-label={`Kelola foto ${model.model_name}`}
                        >
                          <Image size={14} />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleOpenEditModel(model)}
                          aria-label={`Edit ${model.model_name}`}
                        >
                          <Edit2 size={14} />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-rose-600 hover:bg-rose-50"
                          onClick={() => setDeleteTarget({ type: 'model', id: model.id, name: `${model.brand} ${model.model_name}` })}
                          aria-label={`Hapus ${model.model_name}`}
                        >
                          <Trash2 size={14} />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              <Pagination
                currentPage={modelMeta.current_page}
                totalPages={modelMeta.last_page}
                onPageChange={(p) => fetchModels(p)}
              />
            </>
          )}
        </div>
      )}

      {/* TAB 2: TYPES */}
      {activeTab === 'types' && (
        <div className="space-y-4">
          <div className="relative max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
            <Input
              placeholder="Cari kategori alat..."
              value={typeSearch}
              onChange={(e) => setTypeSearch(e.target.value)}
              className="pl-9"
            />
          </div>

          {isLoadingTypes ? (
            <TableSkeleton rows={4} cols={4} />
          ) : types.length === 0 ? (
            <EmptyState
              icon={<Layers size={24} />}
              title="Belum Ada Kategori Tipe Alat"
              description="Belum ada tipe kategori alat berat yang terdaftar."
              action={
                <Button size="sm" onClick={handleOpenCreateType} leftIcon={<Plus size={14} />}>
                  Tambah Tipe Alat
                </Button>
              }
            />
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nama Tipe / Kategori</TableHead>
                    <TableHead>Deskripsi Fungsi</TableHead>
                    <TableHead>Model Terikat</TableHead>
                    <TableHead className="text-right">Aksi</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {types.map((type) => (
                    <TableRow key={type.id}>
                      <TableCell className="font-semibold text-slate-900">
                        {type.name}
                      </TableCell>
                      <TableCell className="text-slate-500 text-sm max-w-md truncate">
                        {type.description || '-'}
                      </TableCell>
                      <TableCell>
                        <span className="text-sm font-medium text-slate-700">
                          {type.models_count ?? 0} Model
                        </span>
                      </TableCell>
                      <TableCell className="text-right space-x-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleOpenEditType(type)}
                          aria-label={`Edit ${type.name}`}
                        >
                          <Edit2 size={14} />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-rose-600 hover:bg-rose-50"
                          onClick={() => setDeleteTarget({ type: 'type', id: type.id, name: type.name })}
                          aria-label={`Hapus ${type.name}`}
                        >
                          <Trash2 size={14} />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              <Pagination
                currentPage={typeMeta.current_page}
                totalPages={typeMeta.last_page}
                onPageChange={(p) => fetchTypes(p)}
              />
            </>
          )}
        </div>
      )}

      {/* MODAL: CREATE / EDIT TYPE */}
      <Modal
        isOpen={isTypeModalOpen}
        onClose={() => setIsTypeModalOpen(false)}
        title={editingType ? 'Edit Kategori Tipe Alat' : 'Tambah Kategori Tipe Baru'}
      >
        <form onSubmit={handleSaveType} className="space-y-4">
          <Input
            label="Nama Tipe / Kategori"
            value={typeName}
            onChange={(e) => setTypeName(e.target.value)}
            error={formErrors.name?.[0]}
            placeholder="Contoh: Excavator, Bulldozer"
            required
          />

          <Textarea
            label="Deskripsi / Fungsi Operasional"
            value={typeDesc}
            onChange={(e) => setTypeDesc(e.target.value)}
            error={formErrors.description?.[0]}
            placeholder="Jelaskan karakteristik fungsional alat..."
            rows={3}
          />

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button variant="outline" type="button" onClick={() => setIsTypeModalOpen(false)}>
              Batal
            </Button>
            <Button type="submit" isLoading={isSaving}>
              {editingType ? 'Simpan Perubahan' : 'Tambah Tipe'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* MODAL: CREATE / EDIT MODEL */}
      <Modal
        isOpen={isModelModalOpen}
        onClose={() => setIsModelModalOpen(false)}
        title={editingModel ? 'Edit Model Armada' : 'Tambah Model Armada Baru'}
        size="lg"
      >
        <form onSubmit={handleSaveModel} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Select
              label="Kategori Tipe Alat"
              value={modelTypeId}
              onChange={(e) => setModelTypeId(e.target.value)}
              error={formErrors.equipment_type_id?.[0]}
              required
            >
              {allTypesList.map((t) => (
                <option key={t.id} value={t.id}>{t.name}</option>
              ))}
            </Select>

            <Input
              label="Merk / Pabrikan"
              value={modelBrand}
              onChange={(e) => setModelBrand(e.target.value)}
              error={formErrors.brand?.[0]}
              placeholder="Contoh: Komatsu, Caterpillar"
              required
            />
          </div>

          <Input
            label="Nama Seri Model"
            value={modelName}
            onChange={(e) => setModelName(e.target.value)}
            error={formErrors.model_name?.[0]}
            placeholder="Contoh: PC200-8, 320D"
            required
          />

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Nilai Kapasitas"
              type="number"
              step="0.01"
              value={capacityValue}
              onChange={(e) => setCapacityValue(e.target.value)}
              error={formErrors.capacity_value?.[0]}
              placeholder="Contoh: 20.00"
              required
            />

            <Select
              label="Satuan Kapasitas"
              value={capacityUnit}
              onChange={(e) => setCapacityUnit(e.target.value)}
              error={formErrors.capacity_unit?.[0]}
              required
            >
              <option value="Ton">Ton</option>
              <option value="m3">m3 (Meter Kubik)</option>
              <option value="HP">HP (Horsepower)</option>
              <option value="Liter">Liter</option>
            </Select>
          </div>

          <div className="pt-2">
            <Switch
              label="Status Aktif Katalog"
              description="Model yang aktif akan muncul di katalog pencarian sewa pengguna."
              checked={modelIsActive}
              onChange={setModelIsActive}
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button variant="outline" type="button" onClick={() => setIsModelModalOpen(false)}>
              Batal
            </Button>
            <Button type="submit" isLoading={isSaving}>
              {editingModel ? 'Simpan Perubahan' : 'Tambah Model'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* CONFIRM DIALOG: DELETE */}
      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleConfirmDelete}
        title={`Konfirmasi Hapus ${deleteTarget?.type === 'type' ? 'Tipe' : 'Model'}`}
        message={`Apakah Anda yakin ingin menghapus "${deleteTarget?.name}"? Tindakan ini akan menonaktifkan master data.`}
        confirmText="Hapus Master"
        variant="danger"
        isLoading={isDeleting}
      />

      {/* MODAL: PHOTO MANAGEMENT */}
      <Modal
        isOpen={!!photoModalModel}
        onClose={() => setPhotoModalModel(null)}
        title={`Foto Armada: ${photoModalModel?.brand} ${photoModalModel?.model_name}`}
        size="lg"
      >
        <div className="space-y-4">
          <div className="flex justify-between items-center bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div>
              <p className="text-xs font-semibold text-slate-700">Unggah Foto Baru</p>
              <p className="text-xs text-slate-500">Format: JPG, PNG, WEBP. Maksimum 5 MB.</p>
            </div>
            <input
              type="file"
              ref={fileInputRef}
              accept="image/jpeg,image/png,image/webp"
              onChange={handleUploadPhoto}
              className="hidden"
            />
            <Button
              size="sm"
              isLoading={isUploadingPhoto}
              onClick={() => fileInputRef.current?.click()}
              leftIcon={<Plus size={14} />}
            >
              Pilih Foto
            </Button>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 max-h-96 overflow-y-auto p-1">
            {photoModalModel?.attachments && photoModalModel.attachments.length > 0 ? (
              photoModalModel.attachments.map((att) => (
                <div key={att.id} className="group relative rounded-xl border border-slate-200 overflow-hidden bg-slate-100 aspect-video">
                  <img src={att.url} alt={att.file_name} className="w-full h-full object-cover" />
                  <div className="absolute inset-0 bg-slate-900/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center p-2">
                    <Button
                      variant="danger"
                      size="sm"
                      onClick={() => photoModalModel && handleDeletePhoto(photoModalModel.id, att.id)}
                      leftIcon={<X size={14} />}
                    >
                      Hapus
                    </Button>
                  </div>
                </div>
              ))
            ) : (
              <div className="col-span-full text-center py-8 text-slate-400 text-sm">
                Belum ada foto yang diunggah untuk model ini.
              </div>
            )}
          </div>
        </div>
      </Modal>
    </div>
  )
}
export default AdminEquipmentMasterPage
