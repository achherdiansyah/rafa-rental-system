import React, { useState, useEffect } from 'react'
import { Users, CheckCircle2, XCircle } from 'lucide-react'
import { userService } from '../services/userService'
import type { AdminUserData } from '../services/userService'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Modal } from '@/components/ui/Modal'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { useToast } from '@/hooks/useToast'

export const AdminUsersPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [users, setUsers] = useState<AdminUserData[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  const [roleFilter, setRoleFilter] = useState('')
  const [roleDraft, setRoleDraft] = useState<Record<number, string>>({})
  const [savingRoleId, setSavingRoleId] = useState<number | null>(null)

  const [createOpen, setCreateOpen] = useState(false)
  const [isCreating, setIsCreating] = useState(false)
  const [createForm, setCreateForm] = useState({
    name: '',
    email: '',
    phone_number: '',
    role: 'SUPERVISOR' as 'SUPERVISOR' | 'OPERATOR',
  })
  const [tempCredential, setTempCredential] = useState<{ email: string; password: string } | null>(null)

  const [verifyTarget, setVerifyTarget] = useState<AdminUserData | null>(null)
  const [rejectTarget, setRejectTarget] = useState<AdminUserData | null>(null)
  const [isActing, setIsActing] = useState(false)

  const loadUsers = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      // Load max 50 recent users for verification
      const res = await userService.getUsers({ role: roleFilter || undefined, per_page: 50 })
      if (res.success && res.data) {
        setUsers(res.data)
      }
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat daftar pengguna.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadUsers()
  }, [roleFilter])

  const handleVerify = async () => {
    if (!verifyTarget) return
    setIsActing(true)
    try {
      const res = await userService.verifyAccount(verifyTarget.id, 'VERIFIED')
      if (res.success) {
        showSuccessToast(`Akun ${verifyTarget.name} berhasil diverifikasi.`)
        loadUsers()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memverifikasi akun.')
    } finally {
      setIsActing(false)
      setVerifyTarget(null)
    }
  }

  const handleReject = async () => {
    if (!rejectTarget) return
    setIsActing(true)
    try {
      const res = await userService.verifyAccount(rejectTarget.id, 'REJECTED')
      if (res.success) {
        showSuccessToast(`Akun ${rejectTarget.name} ditolak.`)
        loadUsers()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menolak akun.')
    } finally {
      setIsActing(false)
      setRejectTarget(null)
    }
  }

  const handleSaveRole = async (user: AdminUserData) => {
    const role = (roleDraft[user.id] ?? user.role) as 'USER' | 'SUPERVISOR' | 'OPERATOR'
    if (role === user.role) return
    setSavingRoleId(user.id)
    try {
      const res = await userService.updateUserRole(user.id, role)
      if (res.success) {
        showSuccessToast(`Role ${user.name} diubah menjadi ${role}.`)
        setRoleDraft((prev) => ({ ...prev, [user.id]: role }))
        loadUsers()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengubah role pengguna.')
    } finally {
      setSavingRoleId(null)
    }
  }

  const EDITABLE_ROLES = ['USER', 'SUPERVISOR', 'OPERATOR']

  const handleCreateStaff = async () => {
    if (!createForm.name.trim() || !createForm.email.trim()) {
      showErrorToast('Nama dan email wajib diisi.')
      return
    }
    setIsCreating(true)
    try {
      const res = await userService.createStaff({
        name: createForm.name,
        email: createForm.email,
        phone_number: createForm.phone_number || undefined,
        role: createForm.role,
        is_active: true,
      })
      if (res.success && res.data) {
        setTempCredential({ email: res.data.email, password: res.data.temporary_password })
        setCreateOpen(false)
        setCreateForm({ name: '', email: '', phone_number: '', role: 'SUPERVISOR' })
        showSuccessToast('Akun staf berhasil dibuat.')
        loadUsers()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal membuat akun staf.')
    } finally {
      setIsCreating(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Kelola Akun &amp; Role Pengguna</h2>
          <p className="text-sm text-slate-500 mt-1">
            Verifikasi identitas pelanggan serta tugaskan role SUPERVISOR atau OPERATOR sesuai kebutuhan operasional.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <select
            value={roleFilter}
            onChange={(e) => setRoleFilter(e.target.value)}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-600"
            aria-label="Filter role pengguna"
          >
            <option value="">Semua Role</option>
            <option value="USER">USER</option>
            <option value="SUPERVISOR">SUPERVISOR</option>
            <option value="OPERATOR">OPERATOR</option>
            <option value="ADMIN">ADMIN</option>
            <option value="OWNER">OWNER</option>
          </select>
          <Button onClick={() => setCreateOpen(true)}>Tambah Akun Staf</Button>
        </div>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Data">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <Card key={i} className="p-5">
              <Skeleton className="h-5 w-1/3 mb-2" />
              <Skeleton className="h-4 w-1/2" />
            </Card>
          ))}
        </div>
      ) : users.length > 0 ? (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          {users.map((user) => {
            const profile = user.customer_profile
            const status = profile?.verification_status || 'UNVERIFIED'
            const isUnverified = status === 'UNVERIFIED'
            
            return (
              <Card key={user.id} className="p-5 flex flex-col justify-between">
                <div className="space-y-3">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <h4 className="text-base font-bold text-slate-900">{user.name}</h4>
                      <p className="text-sm text-slate-500">{user.email}</p>
                    </div>
                    <div className="flex flex-col items-end gap-1.5">
                      <Badge variant={user.role === 'ADMIN' ? 'success' : user.role === 'OWNER' ? 'warning' : 'secondary'} size="sm">
                        {user.role}
                      </Badge>
                      {user.role === 'USER' && (
                        <Badge variant={status === 'VERIFIED' ? 'success' : status === 'REJECTED' ? 'danger' : 'warning'}>
                          {status === 'VERIFIED' ? 'Terverifikasi' : status === 'REJECTED' ? 'Ditolak' : 'Belum Verifikasi'}
                        </Badge>
                      )}
                    </div>
                  </div>

                  {user.role !== 'ADMIN' && user.role !== 'OWNER' && (
                    <div className="flex items-end gap-2">
                      <label className="flex-1 text-xs text-slate-500">
                        <span className="block mb-1">Role Akun</span>
                        <select
                          value={roleDraft[user.id] ?? user.role}
                          onChange={(e) => setRoleDraft((prev) => ({ ...prev, [user.id]: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-600"
                          aria-label={`Role untuk ${user.name}`}
                        >
                          {EDITABLE_ROLES.map((r) => (
                            <option key={r} value={r}>{r}</option>
                          ))}
                        </select>
                      </label>
                      <Button
                        variant="outline"
                        size="sm"
                        isLoading={savingRoleId === user.id}
                        disabled={(roleDraft[user.id] ?? user.role) === user.role}
                        onClick={() => handleSaveRole(user)}
                      >
                        Simpan Role
                      </Button>
                    </div>
                  )}

                  <div className="bg-slate-50 p-3 rounded-lg border border-slate-100 text-sm space-y-1.5">
                    <div className="flex justify-between">
                      <span className="text-slate-500">Telepon / WhatsApp</span>
                      <span className="font-medium text-slate-900">{user.phone_number || '-'}</span>
                    </div>
                    {profile && (
                      <>
                        <div className="flex justify-between">
                          <span className="text-slate-500">Tipe Identitas</span>
                          <span className="font-medium text-slate-900">{profile.identity_type || '-'}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-slate-500">Nomor Identitas</span>
                          <span className="font-medium text-slate-900">{profile.identity_number || '-'}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-slate-500">Instansi / Perusahaan</span>
                          <span className="font-medium text-slate-900">{profile.company_name || '-'}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-slate-500">Alamat Lengkap</span>
                          <span className="font-medium text-slate-900 text-right max-w-[200px] truncate" title={profile.address || ''}>
                            {profile.address || '-'}
                          </span>
                        </div>
                      </>
                    )}
                  </div>
                  
                  <div className="text-xs text-slate-400">
                    Terdaftar sejak: {new Date(user.created_at).toLocaleString('id-ID')}
                  </div>
                </div>

                <div className="mt-4 pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                  {user.role !== 'USER' ? (
                    <span className="text-xs text-slate-400">Akun staf — verifikasi identitas hanya untuk pelanggan.</span>
                  ) : isUnverified ? (
                    <>
                      <Button variant="outline" size="sm" className="text-rose-600 hover:bg-rose-50" onClick={() => setRejectTarget(user)}>
                        <XCircle size={14} className="mr-1" /> Tolak
                      </Button>
                      <Button variant="primary" size="sm" onClick={() => setVerifyTarget(user)}>
                        <CheckCircle2 size={14} className="mr-1" /> Verifikasi Akun
                      </Button>
                    </>
                  ) : (
                    <Button variant="outline" size="sm" disabled>
                      {status === 'VERIFIED' ? 'Sudah Diverifikasi' : 'Ditolak'}
                    </Button>
                  )}
                </div>
              </Card>
            )
          })}
        </div>
      ) : (
        <EmptyState
          icon={<Users className="w-12 h-12" />}
          title="Tidak Ada Pengguna"
          description="Daftar akun pelanggan kosong."
        />
      )}

      {/* Verification Confirm Dialog */}
      <ConfirmDialog
        isOpen={verifyTarget !== null}
        onClose={() => !isActing && setVerifyTarget(null)}
        onConfirm={handleVerify}
        title="Verifikasi Akun Pelanggan"
        message={`Anda yakin ingin memverifikasi akun ${verifyTarget?.name}? Pastikan identitas dan nomor kontak telah dihubungi & sesuai.`}
        confirmText="Verifikasi Akun"
        cancelText="Batal"
        isLoading={isActing}
        variant="primary"
      />

      <Modal isOpen={createOpen} onClose={() => !isCreating && setCreateOpen(false)} title="Tambah Akun Supervisor / Operator" size="sm">
        <div className="space-y-3">
          <Input label="Nama *" value={createForm.name} onChange={(e) => setCreateForm((p) => ({ ...p, name: e.target.value }))} />
          <Input label="Email *" type="email" value={createForm.email} onChange={(e) => setCreateForm((p) => ({ ...p, email: e.target.value }))} />
          <Input label="Nomor Telepon" value={createForm.phone_number} onChange={(e) => setCreateForm((p) => ({ ...p, phone_number: e.target.value }))} />
          <Select label="Role *" value={createForm.role} onChange={(e) => setCreateForm((p) => ({ ...p, role: e.target.value as 'SUPERVISOR' | 'OPERATOR' }))}>
            <option value="SUPERVISOR">SUPERVISOR</option>
            <option value="OPERATOR">OPERATOR</option>
          </Select>
          <p className="text-xs text-slate-500">Password sementara dibuat otomatis dan wajib diganti saat login pertama.</p>
          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <Button variant="outline" onClick={() => setCreateOpen(false)} disabled={isCreating}>Batal</Button>
            <Button onClick={handleCreateStaff} isLoading={isCreating}>Buat Akun</Button>
          </div>
        </div>
      </Modal>

      <Modal isOpen={tempCredential !== null} onClose={() => setTempCredential(null)} title="Password Sementara" size="sm">
        <div className="space-y-3 text-sm">
          <Alert variant="warning" title="Catat sekarang">
            Password sementara hanya ditampilkan sekali. Jangan dikirim melalui saluran tidak aman.
          </Alert>
          <div className="rounded-lg bg-slate-50 border border-slate-100 p-3 font-mono text-xs space-y-1">
            <p>Email: {tempCredential?.email}</p>
            <p>Password: {tempCredential?.password}</p>
          </div>
          <div className="flex justify-end">
            <Button onClick={() => setTempCredential(null)}>Tutup</Button>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={rejectTarget !== null}
        onClose={() => !isActing && setRejectTarget(null)}
        onConfirm={handleReject}
        title="Tolak Verifikasi"
        message={`Tolak akses penyewaan untuk akun ${rejectTarget?.name}? Pengguna ini tidak akan bisa menyewa alat berat.`}
        confirmText="Tolak Akun"
        cancelText="Batal"
        isLoading={isActing}
        variant="danger"
      />
    </div>
  )
}

export default AdminUsersPage
