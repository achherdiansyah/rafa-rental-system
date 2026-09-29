import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { HardHat, ArrowRight, ShieldCheck, Clock, CheckCircle, Truck, Mail, MapPin } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import { useCms } from '@/features/cms/CmsContext'
import type { EquipmentType } from '@/types/equipment'

export const HomePage: React.FC = () => {
  const cms = useCms()
  const [types, setTypes] = useState<EquipmentType[]>([])

  useEffect(() => {
    let mounted = true
    equipmentService
      .getTypes(undefined, true)
      .then((res) => {
        if (mounted) {
          const list = (res.data as unknown as EquipmentType[]) ?? []
          setTypes(Array.isArray(list) ? list : [])
        }
      })
      .catch(() => undefined)
    return () => {
      mounted = false
    }
  }, [])

  const companyLabel = cms.brand_name || 'CV SUMBER MAKMUR RAFA'
  const heroTitle = cms.hero_title || 'Sewa Alat Berat untuk Proyek Anda'
  const heroSubtitle =
    cms.hero_subtitle ||
    'Solusi penyewaan alat berat untuk kebutuhan konstruksi, pertambangan, dan pekerjaan lapangan dengan proses yang praktis dan terpercaya.'
  const ctaText = cms.hero_cta_text || 'Cari Equipment'
  const ctaLink = cms.hero_cta_link || '/app/equipment'
  const heroImage = cms.hero_image || '/hero-equipment.svg'
  const ctaSection = cms.cta_section || 'Hubungi tim kami untuk kebutuhan armada dan penawaran sewa terbaik.'
  const footerText = cms.footer || 'PT RAFA Rental Nusantara. All rights reserved.'

  return (
    <div className="space-y-16">
      {/* HERO — 45/55 split, image dominant */}
      <section id="home" className="grid grid-cols-1 lg:grid-cols-[45fr_55fr] gap-10 lg:gap-14 items-center py-10 lg:py-16 scroll-mt-24">
        <div className="space-y-6 max-w-xl">
          <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 text-slate-500 text-xs font-semibold tracking-wide border border-slate-200">
            <HardHat size={14} className="text-slate-400" /> {companyLabel}
          </div>
          <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 tracking-tight leading-[1.05]">
            {heroTitle}
          </h1>
          <p className="text-lg text-slate-600 leading-relaxed max-w-lg">{heroSubtitle}</p>
          <div className="flex flex-wrap gap-3 pt-1">
            <Link to={ctaLink}>
              <Button size="lg" rightIcon={<ArrowRight size={18} />}>
                {ctaText}
              </Button>
            </Link>
            <Link to="/#equipment">
              <Button variant="outline" size="lg">Lihat Katalog</Button>
            </Link>
          </div>
        </div>
        <div className="relative">
          <div
            className="absolute inset-0 -z-10 rounded-[2rem] bg-gradient-to-b from-slate-100 via-white to-transparent"
            aria-hidden="true"
          />
          <img
            src={heroImage}
            alt="Armada alat berat RAFA Rental"
            className="w-full aspect-[5/4] object-contain lg:aspect-[6/5]" 
          />
        </div>
      </section>

      {/* EQUIPMENT CATEGORIES */}
      <section className="space-y-6 scroll-mt-24" id="equipment">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Kategori Alat Berat</h2>
          <p className="text-sm text-slate-500 mt-1">Pilih kategori armada sesuai kebutuhan proyek Anda.</p>
        </div>
        {types.length > 0 ? (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            {types.map((t) => (
              <Card key={t.id} className="p-5 flex items-center gap-3 hoverable">
                <div className="w-11 h-11 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                  <Truck size={20} />
                </div>
                <div className="min-w-0">
                  <p className="font-semibold text-slate-900 truncate">{t.name}</p>
                  <p className="text-xs text-slate-400 truncate">{t.description || 'Alat berat untuk proyek'}</p>
                </div>
              </Card>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            {['Excavator', 'Bulldozer', 'Wheel Loader', 'Dump Truck', 'Crane', 'Compactor'].map((c) => (
              <Card key={c} className="p-5 flex items-center gap-3 hoverable">
                <div className="w-11 h-11 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                  <Truck size={20} />
                </div>
                <p className="font-semibold text-slate-900">{c}</p>
              </Card>
            ))}
          </div>
        )}
      </section>

      {/* ABOUT */}
      <section className="space-y-4 scroll-mt-24" id="about">
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Tentang Kami</h2>
        <p className="text-slate-600 max-w-3xl leading-relaxed">
          {cms.about || 'Perusahaan penyedia layanan sewa alat berat untuk proyek konstruksi, tambang, dan infrastruktur — dengan proses transparan dan monitoring operasional presisi.'}
        </p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
          {[
            { icon: <Clock size={20} />, title: 'Alokasi Cepat 24 Jam', desc: 'Persetujuan booking dan penerbitan invoice dengan batas waktu jelas.' },
            { icon: <ShieldCheck size={20} />, title: 'Unit Fisik Terinspeksi', desc: 'Seluruh unit melalui uji kelayakan sebelum mobilisasi dan tervalidasi BAST.' },
            { icon: <CheckCircle size={20} />, title: 'Perhitungan Jam Nyata', desc: 'Penagihan Hour Meter akurat berbasis timesheet tervalidasi.' },
          ].map((f) => (
            <Card key={f.title} className="p-6 hoverable">
              <div className="w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center mb-3">{f.icon}</div>
              <h3 className="font-semibold text-slate-900">{f.title}</h3>
              <p className="text-sm text-slate-500 mt-1">{f.desc}</p>
            </Card>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="card-surface p-10 text-center space-y-4">
        <h2 className="text-3xl font-extrabold text-slate-900 tracking-tight">{cms.hero_cta_text ? 'Siap Memulai Proyek Anda?' : 'Siap Memulai Proyek Anda?'}</h2>
        <p className="text-slate-600 max-w-2xl mx-auto leading-relaxed">{ctaSection}</p>
        <div className="flex justify-center gap-3 pt-2">
          <Link to="/register">
            <Button size="lg" leftIcon={<HardHat size={17} />}>Daftar Sekarang</Button>
          </Link>
        </div>
      </section>

      {/* CONTACT */}
      <section className="space-y-4 scroll-mt-24" id="contact">
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Kontak</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
          <div className="flex items-center gap-3 text-slate-600">
            <Mail size={18} className="text-slate-400" /> cs@rafarental.com
          </div>
          <div className="flex items-center gap-3 text-slate-600">
            <MapPin size={18} className="text-slate-400" /> Jakarta, Indonesia
          </div>
        </div>
      </section>

      <footer className="border-t border-slate-200 pt-8 pb-4 text-center text-sm text-slate-400">
        &copy; {new Date().getFullYear()} {footerText}
      </footer>
    </div>
  )
}
export default HomePage
