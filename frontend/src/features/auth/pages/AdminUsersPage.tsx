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
import { useToast } from '@/hooks/useToast'

export const AdminUsersPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [users, setUsers] = useState<AdminUserData[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  
  // Only interested in USER role for account verification
  const [roleFilter] = useState('USER')

  const [verifyTarget, setVerifyTarget] = useState<AdminUserData | null>(null)
  const [rejectTarget, setRejectTarget] = useState<AdminUserData | null>(null)
  const [isActing, setIsActing] = useState(false)

  const loadUsers = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      // Load max 50 recent users for verification
      const res = await userService.getUsers({ role: roleFilter, per_page: 50 })
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

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Verifikasi Akun Pelanggan</h2>
          <p className="text-sm text-slate-500 mt-1">
            Tinjau identitas pengguna, verifikasi nomor telepon, dan berikan akses penyewaan armada.
          </p>
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
                    <Badge variant={status === 'VERIFIED' ? 'success' : status === 'REJECTED' ? 'danger' : 'warning'}>
                      {status === 'VERIFIED' ? 'Terverifikasi' : status === 'REJECTED' ? 'Ditolak' : 'Belum Verifikasi'}
                    </Badge>
                  </div>

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
                  {isUnverified ? (
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
