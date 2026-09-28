import React, { useState, useEffect, useCallback } from 'react'
import { Plus, Edit2, Trash2, Building2 } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Switch } from '@/components/form/Switch'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/data-display/Table'
import { TableSkeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { ErrorState } from '@/components/feedback/ErrorState'
import { useToast } from '@/hooks/useToast'
import { useLatestCall } from '@/hooks/useLatestCall'
import { bankService } from '../services/bankService'
import type { BankAccount } from '@/types/bank'
import type { ApiError } from '@/types/api'

export const AdminBankAccountsPage: React.FC = () => {
  const { success, error: toastError } = useToast()
  const { run } = useLatestCall()

  const [accounts, setAccounts] = useState<BankAccount[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [hasError, setHasError] = useState(false)

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingAccount, setEditingAccount] = useState<BankAccount | null>(null)
  const [bankName, setBankName] = useState('')
  const [accountNumber, setAccountNumber] = useState('')
  const [accountName, setAccountName] = useState('PT RAFA RENTAL NUSANTARA')
  const [isActive, setIsActive] = useState(true)
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})
  const [isSaving, setIsSaving] = useState(false)

  // Delete Dialog
  const [deleteTarget, setDeleteTarget] = useState<BankAccount | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const fetchAccounts = useCallback(async () => {
    setIsLoading(true)
    setHasError(false)
    try {
      const out = await run(async () => {
        const data = await bankService.getAccounts()
        setAccounts(data || [])
      })
      if (out === null) return // stale request — request terbaru yang mengelola UI
      setIsLoading(false)
    } catch {
      setHasError(true)
      setIsLoading(false)
      toastError('Gagal memuat daftar rekening bank perusahaan.')
    }
  }, [toastError, run])

  useEffect(() => {
    fetchAccounts()
  }, [fetchAccounts])

  const handleOpenCreate = () => {
    setEditingAccount(null)
    setBankName('')
    setAccountNumber('')
    setAccountName('PT RAFA RENTAL NUSANTARA')
    setIsActive(true)
    setFormErrors({})
    setIsModalOpen(true)
  }

  const handleOpenEdit = (account: BankAccount) => {
    setEditingAccount(account)
    setBankName(account.bank_name)
    setAccountNumber(account.account_number)
    setAccountName(account.account_name)
    setIsActive(account.is_active)
    setFormErrors({})
    setIsModalOpen(true)
  }

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSaving(true)
    setFormErrors({})

    const payload = {
      bank_name: bankName,
      account_number: accountNumber,
      account_name: accountName,
      is_active: isActive,
    }

    try {
      if (editingAccount) {
        await bankService.updateAccount(editingAccount.id, payload)
        success(`Rekening ${bankName} berhasil diperbarui.`)
      } else {
        await bankService.createAccount(payload)
        success(`Rekening bank baru ${bankName} berhasil didaftarkan.`)
      }
      setIsModalOpen(false)
      fetchAccounts()
    } catch (err) {
      const apiErr = err as ApiError
      if (apiErr.status === 422) {
        setFormErrors(apiErr.errors)
      } else {
        toastError(apiErr.message || 'Gagal menyimpan rekening.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  const handleConfirmDelete = async () => {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await bankService.deleteAccount(deleteTarget.id)
      success(`Rekening ${deleteTarget.bank_name} (${deleteTarget.account_number}) berhasil dihapus.`)
      setDeleteTarget(null)
      fetchAccounts()
    } catch (err) {
      const apiErr = err as ApiError
      toastError(apiErr.message || 'Gagal menghapus rekening bank perusahaan.')
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Rekening Bank Perusahaan</h2>
          <p className="text-sm text-slate-500">Kelola nomor rekening resmi penampungan transfer pembayaran invoice penyewa</p>
        </div>

        <Button onClick={handleOpenCreate} leftIcon={<Plus size={16} />}>
          Daftarkan Rekening Baru
        </Button>
      </div>

      {/* Content */}
      {isLoading ? (
        <TableSkeleton rows={3} cols={5} />
      ) : hasError ? (
        <ErrorState
          title="Gagal memuat data"
          message="Data rekening bank tidak bisa dimuat. Periksa koneksi lalu coba lagi."
          onRetry={fetchAccounts}
        />
      ) : accounts.length === 0 ? (
        <EmptyState
          icon={<Building2 size={24} />}
          title="Belum Ada Rekening Bank"
          description="Daftarkan rekening bank resmi perusahaan untuk tujuan pembayaran invoice penyewa."
          action={
            <Button size="sm" onClick={handleOpenCreate} leftIcon={<Plus size={14} />}>
              Daftarkan Rekening Sekarang
            </Button>
          }
        />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nama Institusi Bank</TableHead>
              <TableHead>Nomor Rekening</TableHead>
              <TableHead>Nama Pemilik Rekening</TableHead>
              <TableHead>Status Pembayaran</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {accounts.map((acc) => (
              <TableRow key={acc.id}>
                <TableCell className="font-semibold text-slate-900">
                  {acc.bank_name}
                </TableCell>
                <TableCell className="font-mono text-sm text-slate-800 font-medium">
                  {acc.account_number}
                </TableCell>
                <TableCell className="text-sm text-slate-700">
                  {acc.account_name}
                </TableCell>
                <TableCell>
                  <Badge variant={acc.is_active ? 'success' : 'secondary'}>
                    {acc.is_active ? 'Aktif (Menerima Transfer)' : 'Nonaktif'}
                  </Badge>
                </TableCell>
                <TableCell className="text-right">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleOpenEdit(acc)}
                    aria-label={`Edit rekening ${acc.bank_name}`}
                  >
                    <Edit2 size={14} />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="text-rose-600 hover:bg-rose-50"
                    onClick={() => setDeleteTarget(acc)}
                    aria-label={`Hapus rekening ${acc.bank_name}`}
                    title="Hapus Rekening"
                  >
                    <Trash2 size={14} />
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}

      {/* MODAL: CREATE / EDIT */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingAccount ? 'Edit Rekening Bank Perusahaan' : 'Daftarkan Rekening Bank Baru'}
      >
        <form onSubmit={handleSave} className="space-y-4">
          <Input
            label="Nama Bank"
            value={bankName}
            onChange={(e) => setBankName(e.target.value)}
            error={formErrors.bank_name?.[0]}
            placeholder="Contoh: BCA, Mandiri, BNI, BRI"
            required
          />

          <Input
            label="Nomor Rekening"
            value={accountNumber}
            onChange={(e) => setAccountNumber(e.target.value)}
            error={formErrors.account_number?.[0]}
            placeholder="Contoh: 1234567890"
            required
          />

          <Input
            label="Nama Pemilik Rekening"
            value={accountName}
            onChange={(e) => setAccountName(e.target.value)}
            error={formErrors.account_name?.[0]}
            placeholder="Contoh: PT RAFA RENTAL NUSANTARA"
            required
          />

          <div className="pt-2">
            <Switch
              label="Status Aktif Rekening"
              description="Hanya rekening aktif yang akan muncul di instruksi pembayaran invoice penyewa."
              checked={isActive}
              onChange={setIsActive}
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button variant="outline" type="button" onClick={() => setIsModalOpen(false)}>
              Batal
            </Button>
            <Button type="submit" isLoading={isSaving}>
              {editingAccount ? 'Simpan Perubahan' : 'Daftarkan Rekening'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* CONFIRM DIALOG: DELETE */}
      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleConfirmDelete}
        title="Konfirmasi Hapus Rekening Bank"
        message={`Apakah Anda yakin ingin menghapus rekening "${deleteTarget?.bank_name}" (${deleteTarget?.account_number})? Rekening yang sudah pernah dipakai transaksi pembayaran tidak dapat dihapus demi menjaga histori keuangan.`}
        confirmText="Hapus Rekening"
        cancelText="Batal"
        variant="danger"
        isLoading={isDeleting}
      />
    </div>
  )
}
export default AdminBankAccountsPage
