import React from 'react'
import { Link } from 'react-router-dom'
import { FileQuestion, Home } from 'lucide-react'
import { Button } from '@/components/ui/Button'

export const NotFoundPage: React.FC = () => {
  return (
    <div className="min-h-[70vh] flex flex-col items-center justify-center text-center p-4">
      <div className="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-4">
        <FileQuestion size={32} />
      </div>
      <h1 className="text-4xl font-extrabold text-slate-900 tracking-tight">404</h1>
      <h2 className="text-xl font-semibold text-slate-700 mt-2">Halaman Tidak Ditemukan</h2>
      <p className="text-sm text-slate-500 max-w-sm mt-1">
        Tautan yang Anda tuju mungkin salah ketik atau telah dipindahkan ke alamat lain.
      </p>
      <div className="mt-6">
        <Link to="/">
          <Button leftIcon={<Home size={16} />}>Kembali ke Beranda</Button>
        </Link>
      </div>
    </div>
  )
}
export default NotFoundPage
