import React from 'react'
import { Link } from 'react-router-dom'
import { HardHat, ArrowRight, ShieldCheck, Clock, CheckCircle } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card'

export const HomePage: React.FC = () => {
  return (
    <div className="space-y-12">
      {/* Hero */}
      <section className="text-center space-y-4 py-8">
        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 text-primary-700 text-xs font-semibold border border-primary-200">
          <HardHat size={14} /> Sistem Manajemen Rental Alat Berat Terpercaya
        </div>
        <h1 className="text-4xl sm:text-5xl font-extrabold text-slate-900 tracking-tight">
          Sewa Armada Alat Berat <br className="hidden sm:inline" />
          <span className="text-primary-600">Mudah, Akurat & Transparan</span>
        </h1>
        <p className="text-lg text-slate-600 max-w-2xl mx-auto">
          Layanan reservasi armada alat berat untuk proyek konstruksi, tambang, dan infrastruktur dengan monitoring operasional presisi.
        </p>
        <div className="flex justify-center gap-3 pt-2">
          <Link to="/register">
            <Button size="lg" rightIcon={<ArrowRight size={18} />}>
              Mulai Sewa Sekarang
            </Button>
          </Link>
          <Link to="/login">
            <Button variant="outline" size="lg">
              Masuk Akun
            </Button>
          </Link>
        </div>
      </section>

      {/* Value Props */}
      <section className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card>
          <CardHeader>
            <div className="w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center mb-2">
              <Clock size={20} />
            </div>
            <CardTitle>Alokasi Cepat 24 Jam</CardTitle>
            <CardDescription>
              Persetujuan booking dan penerbitan invoice resmi terstandarisasi dengan batas waktu jelas.
            </CardDescription>
          </CardHeader>
        </Card>

        <Card>
          <CardHeader>
            <div className="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
              <ShieldCheck size={20} />
            </div>
            <CardTitle>Unit Fisik Terinspeksi</CardTitle>
            <CardDescription>
              Semua unit fisik melalui uji kelayakan ketat sebelum dimobilisasi dan tervalidasi via BAST.
            </CardDescription>
          </CardHeader>
        </Card>

        <Card>
          <CardHeader>
            <div className="w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center mb-2">
              <CheckCircle size={20} />
            </div>
            <CardTitle>Perhitungan Jam Nyata</CardTitle>
            <CardDescription>
              Penagihan Hour Meter (HM) akurat tanpa pembulatan fiktif dan pencatatan timesheet terverifikasi.
            </CardDescription>
          </CardHeader>
        </Card>
      </section>
    </div>
  )
}
export default HomePage
