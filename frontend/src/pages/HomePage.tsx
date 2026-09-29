import React, { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { HardHat, ArrowRight, Truck, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import { useCms } from '@/features/cms/CmsContext'
import {
  fallbackBrand,
  fallbackHero,
  fallbackContent,
  stats,
  categories,
  featuredFallback,
  benefits,
  fallbackNav,
} from '@/features/cms/landingFallbackData'
import type { EquipmentType, EquipmentModel } from '@/types/equipment'

const idr = (n: number): string => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0)

export const HomePage: React.FC = () => {
  const cms = useCms()
  const bootedRef = useRef(false)
  const [types, setTypes] = useState<EquipmentType[]>([])
  const [featured, setFeatured] = useState<EquipmentModel[]>([])

  useEffect(() => {
    if (bootedRef.current) return
    bootedRef.current = true
    let mounted = true
    Promise.all([equipmentService.getTypes(undefined, true), equipmentService.getModels({ per_page: 6, is_active: true })])
      .then(([tRes, mRes]) => {
        if (!mounted) return
        const list = (tRes.data as unknown as EquipmentType[]) ?? []
        setTypes(Array.isArray(list) ? list : [])
        setFeatured(mRes.data ?? [])
      })
      .catch(() => undefined)
    return () => {
      mounted = false
    }
  }, [])

  const brandName = cms.brand_name ?? fallbackBrand.brand_name
  const brandLogo = cms.brand_logo || null
  const companyLabel = cms.brand_name ?? fallbackHero.eyebrow
  const heroTitle = cms.hero_title ?? fallbackHero.title
  const heroSubtitle = cms.hero_subtitle ?? fallbackHero.subtitle
  const ctaText = cms.hero_cta_text ?? fallbackHero.cta_text
  const ctaLink = cms.hero_cta_link ?? fallbackHero.cta_link
  const heroImage = cms.hero_image ?? fallbackHero.image

  const aboutText = cms.about ?? fallbackContent.about
  const ctaSection = cms.cta_section ?? fallbackContent.cta_section
  const footerNote = cms.footer ?? fallbackContent.footer

  return (
    <div className="space-y-16">
      {/* HERO — full-bleed image + thin white overlay */}
      <section id="home" className="relative overflow-hidden scroll-mt-24 w-screen left-1/2 -translate-x-1/2 -mt-8">
        <img
          src={heroImage}
          alt="Armada alat berat RAFA Rental"
          className="absolute inset-0 w-full h-full object-cover"
          loading="eager"
          decoding="async"
        />
        <div className="absolute inset-0 bg-white/45" aria-hidden="true" />
        <div className="relative z-10 max-w-2xl pt-20 pb-28 px-4 sm:px-6 lg:px-8">
          <p className="text-sm font-semibold tracking-wide text-slate-600">{companyLabel}</p>
          <h1 className="mt-4 text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 tracking-tight leading-[1.05]">
            {heroTitle}
          </h1>
          <p className="mt-5 text-lg text-slate-700 leading-relaxed max-w-xl">{heroSubtitle}</p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Link to={ctaLink}>
              <Button size="lg" variant="accent" rightIcon={<ArrowRight size={18} />}>{ctaText}</Button>
            </Link>
            <Link to="/#equipment">
              <Button variant="outline" size="lg">Lihat Katalog</Button>
            </Link>
          </div>
        </div>
      </section>

      {/* STATISTICS — lightweight strip */}
      <section className="border-y border-slate-200 py-10" aria-label="Statistik perusahaan">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
          {stats.map((s) => (
            <div key={s.label}>
              <p className="text-4xl font-extrabold text-slate-900 tracking-tight">{s.value}</p>
              <p className="text-sm text-slate-500 mt-1">{s.label}</p>
            </div>
          ))}
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
              <Link key={t.id} to="/app/equipment">
                <Card className="p-5 flex items-center gap-3 hoverable">
                  <div className="w-11 h-11 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0"><Truck size={20} /></div>
                  <div className="min-w-0">
                    <p className="font-semibold text-slate-900 truncate">{t.name}</p>
                    <p className="text-xs text-slate-400 truncate">{t.description || 'Alat berat untuk proyek'}</p>
                  </div>
                </Card>
              </Link>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            {categories.map((c) => (
              <Card key={c} className="p-5 flex items-center gap-3 hoverable">
                <div className="w-11 h-11 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0"><Truck size={20} /></div>
                <p className="font-semibold text-slate-900">{c}</p>
              </Card>
            ))}
          </div>
        )}
      </section>

      {/* FEATURED EQUIPMENT */}
      <section className="space-y-6" aria-label="Armada unggulan">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Armada Unggulan</h2>
            <p className="text-sm text-slate-500 mt-1">Pilihan armada populer untuk kebutuhan proyek Anda.</p>
          </div>
          <Link to="/app/equipment" className="text-sm font-medium text-primary-700 hover:text-primary-800 inline-flex items-center gap-1">
            Lihat Semua <ChevronRight size={15} />
          </Link>
        </div>
        {featured.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {featured.map((model) => {
              const photo = model.attachments?.find((a) => a.document_type === 'EQUIPMENT_PHOTO')?.url
              const price = model.prices && model.prices.length > 0 ? Math.min(...model.prices.map((p) => p.base_rate)) : null
              return (
                <Link key={model.id} to="/app/equipment" className="group">
                  <Card className="h-full p-0 overflow-hidden hoverable">
                    <div className="aspect-[4/3] bg-slate-100 flex items-center justify-center overflow-hidden">
                      {photo ? <img src={photo} alt={`${model.brand} ${model.model_name}`} className="w-full h-full object-cover" loading="lazy" /> : <Truck size={40} className="text-slate-300" />}
                    </div>
                    <div className="p-5 space-y-2">
                      <h3 className="font-semibold text-slate-900 group-hover:text-primary-700 transition-colors">{model.model_name}</h3>
                      <p className="text-sm text-slate-500">{model.brand}</p>
                      <div className="flex items-center justify-between pt-1">
                        {price !== null ? (
                          <span className="font-mono font-bold text-slate-900">{idr(price)}<span className="text-xs text-slate-400 font-normal"> / jam</span></span>
                        ) : (
                          <span className="text-xs text-slate-400">Cek tarif</span>
                        )}
                        {typeof model.units_count === 'number' && <span className="text-xs text-slate-400">{model.units_count} unit</span>}
                      </div>
                    </div>
                  </Card>
                </Link>
              )
            })}
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {featuredFallback.map((f) => (
              <Card key={f.model} className="p-0 overflow-hidden hoverable">
                <div className="aspect-[4/3] bg-slate-100 flex items-center justify-center text-slate-300"><Truck size={40} /></div>
                <div className="p-5 space-y-2">
                  <h3 className="font-semibold text-slate-900">{f.model}</h3>
                  <p className="text-sm text-slate-500">{f.brand}</p>
                  <p className="font-mono font-bold text-slate-900 pt-1">{f.price}</p>
                </div>
              </Card>
            ))}
          </div>
        )}
      </section>

      {/* ABOUT — split text (CMS) + visual */}
      <section id="about" className="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center scroll-mt-24">
        <div className="space-y-4 max-w-xl">
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Tentang Kami</h2>
          <p className="text-slate-600 leading-relaxed">{aboutText}</p>
          <Link to="/app/equipment">
            <Button variant="outline">Lihat Armada Kami</Button>
          </Link>
        </div>
        {heroImage && (
          <div className="relative">
            <div className="absolute inset-0 -z-10 rounded-[2rem] bg-slate-100" aria-hidden="true" />
            <img src={heroImage} alt={cms.about ? 'Tentang RAFA Rental' : 'Armada RAFA Rental'} className="w-full aspect-[5/4] object-contain" loading="lazy" />
          </div>
        )}
      </section>

      {/* BENEFITS */}
      <section className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Keunggulan Kami</h2>
          <p className="text-sm text-slate-500 mt-1">{cms.services ? 'Layanan dan keunggulan untuk kelancaran proyek Anda.' : 'Alasan memilih layanan sewa armada kami.'}</p>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {benefits.map((b) => (
            <div key={b.title} className="space-y-3">
              <div className="w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">{b.icon}</div>
              <h3 className="font-semibold text-slate-900">{b.title}</h3>
              <p className="text-sm text-slate-500 leading-relaxed">{b.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* CTA band */}
      <section className="bg-slate-900 rounded-2xl px-5 sm:px-12 py-12 text-center space-y-4">
        <h2 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Butuh alat berat untuk proyek Anda?</h2>
        <p className="text-slate-300 max-w-2xl mx-auto leading-relaxed">{ctaSection}</p>
        <div className="flex justify-center pt-2">
          <Link to="/app/equipment">
            <Button size="lg" variant="accent" rightIcon={<ArrowRight size={18} />}>Cari Equipment</Button>
          </Link>
        </div>
      </section>

      {/* CONTACT — minimal, no invented company info */}
      <section id="contact" className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center scroll-mt-24">
        <div className="space-y-3">
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Kontak</h2>
          <p className="text-slate-600 max-w-md leading-relaxed">Siap memulai? Gunakan akun Anda untuk melakukan pemesanan, atau daftar untuk mulai menyewa armada.</p>
        </div>
        <div className="flex flex-wrap gap-3 lg:justify-end">
          <Link to="/login">
            <Button variant="outline" size="lg">Masuk Akun</Button>
          </Link>
          <Link to="/register">
            <Button size="lg">Daftar Sekarang</Button>
          </Link>
        </div>
      </section>

      {/* FOOTER — minimalist */}
      <footer className="border-t border-slate-200 pt-10 pb-4 space-y-8">
        <div className="flex flex-col md:flex-row items-start justify-between gap-8">
          <div className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 text-white overflow-hidden">
              {brandLogo ? <img src={brandLogo} alt={brandName} className="w-full h-full object-contain p-1 bg-white" /> : <HardHat size={20} />}
            </span>
            <span className="font-bold text-lg text-slate-900">{brandName}</span>
          </div>
          <nav aria-label="Navigasi footer" className="flex flex-wrap gap-6">
            {fallbackNav.map((m) => (
              <Link key={m.href} to={m.href} className="text-sm text-slate-600 hover:text-slate-900 transition-colors">
                {m.label}
              </Link>
            ))}
          </nav>
        </div>
        <p className="text-xs text-slate-400">
          &copy; {new Date().getFullYear()} {footerNote}
        </p>
      </footer>
    </div>
  )
}
export default HomePage
