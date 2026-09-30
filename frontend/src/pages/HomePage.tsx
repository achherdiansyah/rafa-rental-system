import React, { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Truck, ChevronRight, ChevronLeft, Tractor, Container, Box, Wrench, MapPin, Phone, Mail, Clock, MessageCircle } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Reveal } from '@/components/ui/Reveal'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import { useCms } from '@/features/cms/CmsContext'
import { scrollToElementId } from '@/hooks/useHashScroll'
import { fallbackHero, fallbackContent, categories, featuredFallback, benefits } from '@/features/cms/landingFallbackData'
import type { EquipmentType, EquipmentModel } from '@/types/equipment'

const STATS: Array<{ count?: number; suffix?: string; staticText?: string; label: string }> = [
  { count: 5, suffix: '+', label: 'Unit Alat Berat' },
  { count: 30, suffix: '+', label: 'Proyek Terlayani' },
  { count: 100, suffix: '%', label: 'Kualitas Terjaga' },
  { staticText: '24/7', label: 'Dukungan Pelanggan' },
]

function CountUp({ value, suffix = '', duration = 1500 }: { value: number; suffix?: string; duration?: number }) {
  const ref = useRef<HTMLSpanElement>(null)
  const [display, setDisplay] = useState(0)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    if (typeof IntersectionObserver === 'undefined') {
      setDisplay(value) // non-browser environments (tests)
      return
    }
    let raf = 0
    const io = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) return
        io.disconnect()
        const start = performance.now()
        const tick = (t: number) => {
          const p = Math.min((t - start) / duration, 1)
          setDisplay(Math.round(value * p))
          if (p < 1) raf = requestAnimationFrame(tick)
        }
        raf = requestAnimationFrame(tick)
      },
      { threshold: 0.4 }
    )
    io.observe(el)
    return () => {
      io.disconnect()
      cancelAnimationFrame(raf)
    }
  }, [value, duration])

  return (
    <span ref={ref}>
      {display}
      {suffix}
    </span>
  )
}

const CATEGORY_ICON = { Excavator: Truck, Bulldozer: Tractor, 'Wheel Loader': Container, 'Dump Truck': Truck, Crane: Wrench, Compactor: Box } as Record<string, typeof Truck>
const categoryIcon = (name: string) => {
  const Icon = CATEGORY_ICON[name] ?? Truck
  return <Icon size={20} />
}

function Typewriter({ text }: { text: string }) {
  const ref = useRef<HTMLSpanElement>(null)
  const [len, setLen] = useState(0)
  const [done, setDone] = useState(false)

  useEffect(() => {
    setLen(0)
    setDone(false)
    const el = ref.current
    if (!el) return
    if (
      typeof IntersectionObserver === 'undefined' ||
      (typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches)
    ) {
      setLen(text.length)
      setDone(true)
      return
    }
    let raf = 0
    const io = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) return
        io.disconnect()
        const dur = Math.min(1500, Math.max(400, text.length * 32))
        const start = performance.now()
        const tick = (t: number) => {
          const p = Math.min((t - start) / dur, 1)
          setLen(Math.round(text.length * p))
          if (p < 1) raf = requestAnimationFrame(tick)
          else setDone(true)
        }
        raf = requestAnimationFrame(tick)
      },
      { threshold: 0.4 }
    )
    io.observe(el)
    return () => {
      io.disconnect()
      cancelAnimationFrame(raf)
    }
  }, [text])

  return (
    <span ref={ref} aria-label={text}>
      {text.slice(0, len)}
      <span className={`type-caret ${done ? 'done' : ''}`}>|</span>
    </span>
  )
}

export const HomePage: React.FC = () => {
  const cms = useCms()
  const bootedRef = useRef(false)
  const featuredScroll = useRef<HTMLDivElement>(null)
  const [types, setTypes] = useState<EquipmentType[]>([])
  const [featured, setFeatured] = useState<EquipmentModel[]>([])

  const loadEquipmentData = useCallback(() => {
    Promise.all([equipmentService.getTypes(undefined, true), equipmentService.getModels({ per_page: 12, is_active: true })])
      .then(([tRes, mRes]) => {
        const list = (tRes.data as unknown as EquipmentType[]) ?? []
        setTypes(Array.isArray(list) ? list : [])
        setFeatured(mRes.data ?? [])
      })
      .catch(() => undefined)
  }, [])

  useEffect(() => {
    if (bootedRef.current) return
    bootedRef.current = true
    let mounted = true
    const load = () => {
      Promise.all([equipmentService.getTypes(undefined, true), equipmentService.getModels({ per_page: 12, is_active: true })])
        .then(([tRes, mRes]) => {
          if (!mounted) return
          const list = (tRes.data as unknown as EquipmentType[]) ?? []
          setTypes(Array.isArray(list) ? list : [])
          setFeatured(mRes.data ?? [])
        })
        .catch(() => undefined)
    }
    load()
    return () => {
      mounted = false
    }
  }, [])

  // Refetch equipment on tab focus so photos uploaded from the Admin CMS /
  // Master Armada page appear in this section immediately on returning.
  useEffect(() => {
    window.addEventListener('focus', loadEquipmentData)
    return () => window.removeEventListener('focus', loadEquipmentData)
  }, [loadEquipmentData])

  const companyLabel = cms.brand_name ?? fallbackHero.eyebrow
  const heroTitle = cms.hero_title ?? fallbackHero.title
  const heroSubtitle = cms.hero_subtitle ?? fallbackHero.subtitle
  const ctaText = cms.hero_cta_text ?? fallbackHero.cta_text
  const ctaLink = '/app/equipment' // arahkan selalu ke catalog existing
  const heroImage = cms.hero_image ?? fallbackHero.image

  const aboutText = cms.about ?? fallbackContent.about
  const ctaSection = cms.cta_section ?? fallbackContent.cta_section
  const contact = {
    address: cms.address ?? fallbackContent.address,
    phone: cms.phone ?? fallbackContent.phone,
    whatsapp: cms.whatsapp ?? fallbackContent.whatsapp,
    email: cms.email ?? fallbackContent.email,
    hours: cms.hours ?? fallbackContent.hours,
    waText: cms.whatsapp_cta_text ?? fallbackContent.whatsapp_cta_text,
    waLink: cms.whatsapp_cta_link ?? fallbackContent.whatsapp_cta_link,
  }

  const hasPhoto = (m: EquipmentModel) => Boolean(m.attachments?.find((a) => a.url))
  const orderedFeatured = [...featured.filter(hasPhoto), ...featured.filter((m) => !hasPhoto(m))].slice(0, 8)

  const featuredCards =
    featured.length > 0
      ? orderedFeatured.map((model) => {
          const photo = model.attachments?.find((a) => a.url && a.document_type === 'EQUIPMENT_PHOTO')?.url ?? model.attachments?.find((a) => a.url)?.url
          return {
            key: String(model.id),
            node: (
              <Link to="/app/equipment" className="group">
                <Card className="h-full p-0 overflow-hidden hoverable shadow-md hover:shadow-xl">
                  <div className="aspect-[4/3] bg-slate-100 flex items-center justify-center overflow-hidden">
                    {photo ? <img src={photo} alt={`${model.brand} ${model.model_name}`} className="w-full h-full object-cover" loading="lazy" /> : <Truck size={40} className="text-slate-300" />}
                  </div>
                  <div className="p-5 space-y-1.5">
                    <h3 className="font-semibold text-slate-900 group-hover:text-primary-700 transition-colors">{model.model_name}</h3>
                    <p className="text-sm text-slate-500">{model.brand}</p>
                    {typeof model.units_count === 'number' && <p className="text-xs text-slate-400 pt-1">{model.units_count} unit</p>}
                  </div>
                </Card>
              </Link>
            ),
          }
        })
      : featuredFallback.map((f) => ({
          key: f.model,
          node: (
            <Card className="h-full p-0 overflow-hidden hoverable shadow-md hover:shadow-xl">
              <div className="aspect-[4/3] bg-slate-100 flex items-center justify-center text-slate-300"><Truck size={40} /></div>
              <div className="p-5 space-y-1.5">
                <h3 className="font-semibold text-slate-900">{f.model}</h3>
                <p className="text-sm text-slate-500">{f.brand}</p>
              </div>
            </Card>
          ),
        }))

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
        <div className="absolute inset-0 bg-black/50" aria-hidden="true" />
        <div className="relative z-10 max-w-2xl pt-20 pb-28 pl-6 sm:pl-16 lg:pl-28 pr-6">
          <Reveal delay={0}>
            <p className="text-sm font-semibold tracking-wide text-white">{companyLabel}</p>
          </Reveal>
          <h1 className="mt-4 text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white tracking-tight leading-[1.05]">
            <Typewriter text={heroTitle} />
          </h1>
          <Reveal delay={120}>
            <p className="mt-5 text-lg text-slate-100 leading-relaxed max-w-xl">{heroSubtitle}</p>
          </Reveal>
          <Reveal delay={200}>
            <div className="mt-8 flex flex-wrap gap-3">
              <Link to={ctaLink}>
                <Button size="lg" variant="accent" rightIcon={<ArrowRight size={18} />}>{ctaText}</Button>
              </Link>
              <a
                href="#equipment"
                onClick={(e) => {
                  e.preventDefault()
                  scrollToElementId('equipment')
                }}
              >
                <Button variant="outline" size="lg" className="bg-white/10 border-white/60 text-white hover:bg-white/20 hover:text-white">
                  Lihat Katalog
                </Button>
              </a>
            </div>
          </Reveal>
        </div>
      </section>

      {/* STATISTICS — count-up strip */}
      <section className="border-y border-slate-200 bg-white py-12" aria-label="Statistik perusahaan">
        <div className="grid grid-cols-2 gap-y-10 lg:grid-cols-4 lg:divide-x lg:divide-slate-100">
          {STATS.map((s) => (
            <div key={s.label} className="text-center px-4">
              <p className="text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight">
                {s.staticText ?? <CountUp value={s.count ?? 0} suffix={s.suffix ?? ''} />}
              </p>
              <p className="mt-2 text-sm font-medium uppercase tracking-wide text-slate-500">{s.label}</p>
            </div>
          ))}
        </div>
      </section>

      {/* EQUIPMENT CATEGORIES */}
      <section className="space-y-6 scroll-mt-24" id="equipment">
        <Reveal>
          <div>
            <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Kategori Alat Berat</h2>
            <p className="text-sm text-slate-500 mt-1">Pilih kategori armada sesuai kebutuhan proyek Anda.</p>
          </div>
        </Reveal>
        {types.length > 0 ? (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            {types.map((t, i) => (
              <Reveal key={t.id} delay={i * 70} className="h-full">
                <Link to="/app/equipment" className="h-full block">
                  <Card className="p-5 flex items-center gap-3 hoverable shadow-md hover:shadow-xl h-full">
                    <div className="w-11 h-11 rounded-xl bg-accent-50 text-accent-500 flex items-center justify-center shrink-0">{categoryIcon(t.name)}</div>
                    <div className="min-w-0">
                      <p className="font-semibold text-slate-900 truncate">{t.name}</p>
                      <p className="text-xs text-slate-400 truncate">{t.description || 'Alat berat untuk proyek'}</p>
                    </div>
                  </Card>
                </Link>
              </Reveal>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            {categories.map((c, i) => (
              <Reveal key={c} delay={i * 70}>
                <Card className="p-5 flex items-center gap-3 hoverable shadow-md hover:shadow-xl">
                  <div className="w-11 h-11 rounded-xl bg-accent-50 text-accent-500 flex items-center justify-center shrink-0">{categoryIcon(c)}</div>
                  <p className="font-semibold text-slate-900">{c}</p>
                </Card>
              </Reveal>
            ))}
          </div>
        )}
      </section>

      {/* FEATURED EQUIPMENT */}
      <section className="space-y-6" aria-label="Armada unggulan">
        <Reveal>
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Armada Unggulan</h2>
              <p className="text-sm text-slate-500 mt-1">Pilihan armada populer untuk kebutuhan proyek Anda.</p>
            </div>
            <Link to="/app/equipment" className="text-sm font-medium text-primary-700 hover:text-primary-800 inline-flex items-center gap-1">
              Lihat Semua <ChevronRight size={15} />
            </Link>
          </div>
        </Reveal>
<div className="relative">
          <button
            type="button"
            aria-label="Armada sebelumnya"
            onClick={() => featuredScroll.current?.scrollBy({ left: -340, behavior: 'smooth' })}
            className="absolute -left-3 top-1/2 -translate-y-1/2 z-10 w-9 h-9 rounded-full bg-white border border-slate-200 shadow-md text-slate-600 hover:text-slate-900 flex items-center justify-center cursor-pointer"
          >
            <ChevronLeft size={18} />
          </button>
          <div ref={featuredScroll} className="flex gap-4 overflow-x-auto snap-x snap-mandatory no-scrollbar pb-2">
            {featuredCards.map((c, i) => (
              <div key={c.key} className="w-[290px] sm:w-[320px] shrink-0 snap-start">
                <Reveal delay={i * 60} className="h-full">{c.node}</Reveal>
              </div>
            ))}
          </div>
          <button
            type="button"
            aria-label="Armada berikutnya"
            onClick={() => featuredScroll.current?.scrollBy({ left: 340, behavior: 'smooth' })}
            className="absolute -right-3 top-1/2 -translate-y-1/2 z-10 w-9 h-9 rounded-full bg-white border border-slate-200 shadow-md text-slate-600 hover:text-slate-900 flex items-center justify-center cursor-pointer"
          >
            <ChevronRight size={18} />
          </button>
        </div>
      </section>

      {/* ABOUT — split text (CMS) + visual */}
      <section id="about" className="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center scroll-mt-24">
        <Reveal delay={0}>
          <div className="space-y-4 max-w-xl">
            <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Tentang Kami</h2>
            <p className="text-slate-600 leading-relaxed">{aboutText}</p>
            <Link to="/app/equipment">
              <Button variant="outline">Lihat Armada Kami</Button>
            </Link>
          </div>
        </Reveal>
        {heroImage && (
          <Reveal delay={120}>
            <div className="relative">
              <div className="absolute inset-0 -z-10 rounded-[2rem] bg-slate-100" aria-hidden="true" />
              <img src={heroImage} alt={cms.about ? 'Tentang RAFA Rental' : 'Armada RAFA Rental'} className="w-full aspect-[5/4] object-cover rounded-2xl" loading="lazy" />
            </div>
          </Reveal>
        )}
      </section>

      {/* BENEFITS */}
      <section className="space-y-6">
        <Reveal>
          <div>
            <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Keunggulan Kami</h2>
            <p className="text-sm text-slate-500 mt-1">{cms.services ? 'Layanan dan keunggulan untuk kelancaran proyek Anda.' : 'Alasan memilih layanan sewa armada kami.'}</p>
          </div>
        </Reveal>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {benefits.map((b, i) => (
            <Reveal key={b.title} delay={i * 80}>
              <div className="space-y-3">
                <div className="w-10 h-10 rounded-lg bg-accent-50 text-accent-500 flex items-center justify-center">{b.icon}</div>
                <h3 className="font-semibold text-slate-900">{b.title}</h3>
                <p className="text-sm text-slate-500 leading-relaxed">{b.desc}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </section>

      {/* CTA — teks + button tetap, tanpa card */}
      <section className="text-center space-y-4 py-6">
        <h2 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Butuh alat berat untuk proyek Anda?</h2>
        <p className="text-slate-600 max-w-2xl mx-auto leading-relaxed">{ctaSection}</p>
        <div className="flex justify-center pt-1">
          <Link to="/app/equipment">
            <Button size="lg" variant="accent" rightIcon={<ArrowRight size={18} />}>Cari Equipment</Button>
          </Link>
        </div>
      </section>

      {/* CONTACT — light, 2-col, selaras dgn footer */}
      <section id="contact" className="scroll-mt-24 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16 lg:py-24 grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20">
          {/* LEFT — heading + CTA */}
          <Reveal delay={0}>
            <div className="space-y-6 max-w-xl">
              <div className="space-y-3">
                <h2 className="text-3xl font-bold text-slate-900 tracking-tight">Hubungi Kami</h2>
                <p className="text-slate-600 leading-relaxed">
                  Punya kebutuhan alat berat untuk proyek Anda? Hubungi tim RAFA Rental untuk mendapatkan informasi mengenai armada dan kebutuhan rental Anda.
                </p>
              </div>
              <a href={contact.waLink} target="_blank" rel="noreferrer" className="inline-block">
                <Button size="lg" variant="accent" leftIcon={<MessageCircle size={18} />}>Hubungi via WhatsApp</Button>
              </a>
            </div>
          </Reveal>

          {/* RIGHT — info kontak */}
          <Reveal delay={120} className="lg:pt-4">
            <div className="divide-y divide-slate-100">
              <div className="flex items-center gap-4 py-4">
                <MapPin size={18} className="text-accent-500 shrink-0" />
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Alamat</p>
                  <p className="text-sm text-slate-700 mt-0.5">{contact.address}</p>
                </div>
              </div>
              <div className="flex items-center gap-4 py-4">
                <Phone size={18} className="text-accent-500 shrink-0" />
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Telepon</p>
                  <p className="text-sm text-slate-700 mt-0.5">{contact.phone}</p>
                </div>
              </div>
              <div className="flex items-center gap-4 py-4">
                <MessageCircle size={18} className="text-accent-500 shrink-0" />
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-slate-400">WhatsApp</p>
                  <p className="text-sm text-slate-700 mt-0.5">{contact.whatsapp}</p>
                </div>
              </div>
              <div className="flex items-center gap-4 py-4">
                <Mail size={18} className="text-accent-500 shrink-0" />
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Email</p>
                  <p className="text-sm text-slate-700 mt-0.5 break-all">{contact.email}</p>
                </div>
              </div>
              <div className="flex items-center gap-4 py-4">
                <Clock size={18} className="text-accent-500 shrink-0" />
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Jam Operasional</p>
                  <p className="text-sm text-slate-700 mt-0.5">{contact.hours}</p>
                </div>
              </div>
            </div>
          </Reveal>
        </div>
      </section>
    </div>
  )
}
export default HomePage
