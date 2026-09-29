import React from 'react'
import { Link } from 'react-router-dom'
import { HardHat, ArrowRight, ShieldCheck, Clock, CheckCircle } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card'
import { useCms } from '@/features/cms/CmsContext'

export const HomePage: React.FC = () => {
  const cms = useCms()

  const heroTitle = cms.hero_title || 'Sewa Armada Alat Berat Mudah, Akurat & Transparan'
  const heroSubtitle =
    cms.hero_subtitle ||
    'Layanan reservasi armada alat berat untuk proyek konstruksi, tambang, dan infrastruktur dengan monitoring operasional presisi.'
  const ctaText = cms.hero_cta_text || 'Mulai Sewa Sekarang'
  const ctaLink = cms.hero_cta_link || '/register'

  return (
    <div className="space-y-12">
      {/* Hero */}
      <section className="text-center space-y-4 py-8">
        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 text-primary-700 text-xs font-semibold border border-primary-200">
          <HardHat size={14} /> Sistem Manajemen Rental Alat Berat Terpercaya
        </div>
        <h1 className="text-4xl sm:text-5xl font-extrabold text-slate-900 tracking-tight">{heroTitle}</h1>
        <p className="text-lg text-slate-600 max-w-2xl mx-auto">{heroSubtitle}</p>
        <div className="flex justify-center gap-3 pt-2">
          <Link to={ctaLink}>
            <Button size="lg" rightIcon={<ArrowRight size={18} />}>
              {ctaText}
            </Button>
          </Link>
          <Link to="/login">
            <Button variant="outline" size="lg">
              Masuk Akun
            </Button>
          </Link>
        </div>
        {cms.hero_image && (
          <img src={cms.hero_image} alt="Hero RAFA Rental" className="mx-auto mt-6 max-w-3xl rounded-3xl shadow-lg object-cover" />
        )}
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
