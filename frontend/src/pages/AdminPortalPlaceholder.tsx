import React from 'react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'

export const AdminPortalPlaceholder: React.FC = () => {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Meja Kerja Operasional</h2>
          <p className="text-sm text-slate-500">Antarmuka tugas harian Admin armada dan verifikasi lapangan</p>
        </div>
        <Badge variant="success">ADMIN WORKSPACE</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Antrean Tindakan Cepat Operasional</CardTitle>
          <CardDescription>
            Menampilkan antrean booking baru, status dispatch unit, dan review timesheet.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-slate-600">
            Meja kerja operasional siap diintegrasikan dengan Action controller backend di Phase 5.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
export default AdminPortalPlaceholder
