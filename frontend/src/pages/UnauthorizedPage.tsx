import React from 'react'
import { Link } from 'react-router-dom'
import { Lock, LogIn } from 'lucide-react'
import { Button } from '@/components/ui/Button'

export const UnauthorizedPage: React.FC = () => {
  return (
    <div className="min-h-[70vh] flex flex-col items-center justify-center text-center p-4">
      <div className="w-16 h-16 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-4">
        <Lock size={32} />
      </div>
      <h1 className="text-4xl font-extrabold text-slate-900 tracking-tight">401</h1>
      <h2 className="text-xl font-semibold text-slate-700 mt-2">Sesi Tidak Terautentikasi</h2>
      <p className="text-sm text-slate-500 max-w-sm mt-1">
        Sesi Anda telah kedaluwarsa atau Anda belum masuk. Silakan login terlebih dahulu.
      </p>
      <div className="mt-6">
        <Link to="/login">
          <Button leftIcon={<LogIn size={16} />}>Masuk Akun</Button>
        </Link>
      </div>
    </div>
  )
}
export default UnauthorizedPage
