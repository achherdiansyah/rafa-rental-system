import React from 'react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'

export const OwnerPortalPlaceholder: React.FC = () => {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Executive Dashboard</h2>
          <p className="text-sm text-slate-500">Supervisi finansial, laporan utilisasi armada, dan audit trail</p>
        </div>
        <Badge variant="warning">OWNER EXECUTIVE</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Metrik Finansial & Utilisasi Unit</CardTitle>
          <CardDescription>
            Tinjauan laba kotor, piutang sewa berjalan (AR), dan tingkat okupansi armada.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-slate-600">
            Executive dashboard siap diintegrasikan dengan query agregasi laporan owner di Phase 5.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
export default OwnerPortalPlaceholder
