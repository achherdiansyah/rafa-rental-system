import React from 'react'
import { Link } from 'react-router-dom'
import { ShieldAlert, ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/Button'

export const ForbiddenPage: React.FC = () => {
  return (
    <div className="min-h-[70vh] flex flex-col items-center justify-center text-center p-4">
      <div className="w-16 h-16 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mb-4">
        <ShieldAlert size={32} />
      </div>
      <h1 className="text-4xl font-extrabold text-slate-900 tracking-tight">403</h1>
      <h2 className="text-xl font-semibold text-slate-700 mt-2">Akses Ditolak</h2>
      <p className="text-sm text-slate-500 max-w-sm mt-1">
        Peran (Role) akun Anda saat ini tidak memiliki izin untuk mengakses direktori atau modul ini.
      </p>
      <div className="mt-6 flex gap-3">
        <Button variant="outline" onClick={() => window.history.back()} leftIcon={<ArrowLeft size={16} />}>
          Kembali
        </Button>
        <Link to="/">
          <Button variant="primary">Beranda Utama</Button>
        </Link>
      </div>
    </div>
  )
}
export default ForbiddenPage
