import React from 'react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'

export const UserPortalPlaceholder: React.FC = () => {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Portal Pelanggan</h2>
          <p className="text-sm text-slate-500">Selamat datang di antarmuka pelanggan RAFA Rental</p>
        </div>
        <Badge variant="default">USER PORTAL</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Ringkasan Akun & Status Sewa</CardTitle>
          <CardDescription>
            Informasi reservasi armada aktif dan tagihan invoice berjalan Anda.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-slate-600">
            Halaman portal pelanggan siap dihubungkan dengan endpoint booking dan katalog alat di Phase 5.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
export default UserPortalPlaceholder
