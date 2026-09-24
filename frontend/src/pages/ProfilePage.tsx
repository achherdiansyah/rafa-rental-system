import React, { useEffect, useState } from 'react'
import { useAuth } from '@/hooks/useAuth'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Button } from '@/components/ui/Button'
import { FormSkeleton } from '@/components/ui/Skeleton'
import { ErrorState } from '@/components/feedback/ErrorState'
import { useToast } from '@/hooks/useToast'
import { api } from '@/lib/api'
import type { UserProfile, UpdateProfilePayload } from '@/types/user'
import type { ApiError } from '@/types/api'

export const ProfilePage: React.FC = () => {
  const { refreshUser } = useAuth()
  const { success, error: toastError } = useToast()
  
  const [profile, setProfile] = useState<UserProfile | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isError, setIsError] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({})

  // Form State
  const [formData, setFormData] = useState<UpdateProfilePayload>({})

  useEffect(() => {
    const fetchProfile = async () => {
      setIsLoading(true)
      setIsError(false)
      try {
        const response = await api.get<UserProfile>('/profile')
        setProfile(response.data)
        setFormData({
          name: response.data.name,
          phone_number: response.data.phone_number || '',
          company_name: response.data.customer_profile?.company_name || '',
          identity_number: response.data.customer_profile?.identity_number || '',
          address: response.data.customer_profile?.address || '',
        })
      } catch {
        setIsError(true)
      } finally {
        setIsLoading(false)
      }
    }
    fetchProfile()
  }, [])

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }))
  }

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSaving(true)
    setValidationErrors({})

    try {
      await api.put('/profile', formData)
      success('Data profil Anda berhasil diperbarui.')
      await refreshUser() // Sync global auth context
    } catch (err) {
      const apiErr = err as ApiError
      if (apiErr.status === 422) {
        setValidationErrors(apiErr.errors)
        toastError('Terdapat kesalahan pada isian profil Anda.')
      } else {
        toastError(apiErr.message || 'Gagal menyimpan profil.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  if (isLoading) return <FormSkeleton fields={5} className="max-w-3xl" />
  if (isError || !profile) return <ErrorState message="Gagal memuat profil. Silakan coba lagi." onRetry={() => window.location.reload()} />

  const kycStatus = profile.customer_profile?.verification_status || 'UNVERIFIED'

  return (
    <div className="space-y-6 max-w-3xl">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Profil Saya</h2>
          <p className="text-sm text-slate-500">Kelola informasi identitas dan detail kontak Anda</p>
        </div>
        <div className="flex items-center gap-2 text-sm font-medium">
          Status KYC: 
          <Badge variant={kycStatus === 'VERIFIED' ? 'success' : kycStatus === 'REJECTED' ? 'danger' : 'warning'}>
            {kycStatus}
          </Badge>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Informasi Pribadi & Kontak</CardTitle>
          <CardDescription>
            Email dan role tidak dapat diubah oleh pelanggan.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSave} className="space-y-5">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <Input
                label="Nama Lengkap"
                name="name"
                value={formData.name || ''}
                onChange={handleChange}
                error={validationErrors.name?.[0]}
                required
              />
              <Input
                label="Email (Tidak dapat diubah)"
                value={profile.email}
                disabled
              />
            </div>

            <Input
              label="Nomor Telepon / WhatsApp"
              name="phone_number"
              value={formData.phone_number || ''}
              onChange={handleChange}
              error={validationErrors.phone_number?.[0]}
              required
            />

            <div className="pt-4 border-t border-slate-100">
              <h4 className="text-sm font-semibold text-slate-900 mb-4">Data Institusi & Identitas KYC</h4>
              <div className="space-y-5">
                <Input
                  label="Nama Perusahaan (Opsional)"
                  name="company_name"
                  value={formData.company_name || ''}
                  onChange={handleChange}
                  error={validationErrors.company_name?.[0]}
                />
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <Input
                    label="Tipe Identitas"
                    value={profile.customer_profile?.identity_type || 'KTP'}
                    disabled
                  />
                  <Input
                    label="Nomor Identitas (NIK/KTP)"
                    name="identity_number"
                    value={formData.identity_number || ''}
                    onChange={handleChange}
                    error={validationErrors.identity_number?.[0]}
                    required
                  />
                </div>

                <Input
                  label="Alamat Lengkap"
                  name="address"
                  value={formData.address || ''}
                  onChange={handleChange}
                  error={validationErrors.address?.[0]}
                />
              </div>
            </div>

            <div className="pt-2 flex justify-end">
              <Button type="submit" isLoading={isSaving}>
                Simpan Perubahan
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
export default ProfilePage
