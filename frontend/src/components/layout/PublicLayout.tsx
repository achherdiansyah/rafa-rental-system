import React, { useEffect } from 'react'
import { Link, Outlet } from 'react-router-dom'
import { HardHat, MapPin, Phone, Mail, Clock } from 'lucide-react'
import { Navbar } from './Navbar'
import { CmsProvider, useCms } from '@/features/cms/CmsContext'
import { fallbackContent } from '@/features/cms/landingFallbackData'
import { useHashScroll } from '@/hooks/useHashScroll'

const DEFAULT_NAV = [
  { label: 'Beranda', href: '/' },
  { label: 'Equipment', href: '/#equipment' },
  { label: 'Tentang Kami', href: '/#about' },
  { label: 'Kontak', href: '/#contact' },
]

const COMPANY_DESC =
  'Penyedia layanan sewa alat berat untuk proyek konstruksi, pertambangan, dan infrastruktur dengan proses transparan dan monitoring operasional presisi.'

function BrandFooter() {
  const cms = useCms()
  const brand = cms.brand_name || 'CV SUMBER MAKMUR RAFA'
  const brandLogo = cms.brand_logo || null
  const footerNote = cms.footer || 'PT RAFA Rental Nusantara. All rights reserved.'
  const contact = {
    address: cms.address ?? fallbackContent.address,
    phone: cms.phone ?? fallbackContent.phone,
    whatsapp: cms.whatsapp ?? fallbackContent.whatsapp,
    email: cms.email ?? fallbackContent.email,
    hours: cms.hours ?? fallbackContent.hours,
  }
  let nav = DEFAULT_NAV
  try {
    const parsed = cms.navbar ? JSON.parse(cms.navbar) : []
    if (Array.isArray(parsed) && parsed.length > 0) {
      nav = parsed.filter((m: { label?: unknown; href?: unknown }) => m && typeof m.label === 'string' && typeof m.href === 'string')
    }
  } catch {
    nav = DEFAULT_NAV
  }

  return (
    <footer className="bg-slate-950 text-slate-300">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">
          {/* 1 — brand + deskripsi */}
          <div className="space-y-4">
            <div className="flex items-center gap-2.5">
              <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-600 text-slate-950 overflow-hidden">
                {brandLogo ? <img src={brandLogo} alt={brand} className="w-full h-full object-contain p-1 bg-white" /> : <HardHat size={22} />}
              </span>
              <span className="font-bold text-lg text-slate-100">{brand}</span>
            </div>
            <p className="text-sm text-slate-400 leading-relaxed max-w-xs">{COMPANY_DESC}</p>
          </div>

          {/* 2 — navigasi */}
          <nav aria-label="Navigasi footer" className="space-y-3">
            <h3 className="text-sm font-semibold text-slate-100 uppercase tracking-wide">Navigasi</h3>
            <ul className="space-y-2.5">
              {nav.map((m) => (
                <li key={m.href}>
                  <Link to={m.href} className="text-sm text-slate-400 hover:text-accent-400 transition-colors">
                    {m.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          {/* 3 — kontak */}
          <div className="space-y-3">
            <h3 className="text-sm font-semibold text-slate-100 uppercase tracking-wide">Informasi Kontak</h3>
            <ul className="space-y-2.5">
              {contact.address && (
                <li className="flex items-start gap-2.5 text-sm text-slate-400">
                  <MapPin size={15} className="mt-0.5 shrink-0 text-slate-500" /> {contact.address}
                </li>
              )}
              {contact.phone && (
                <li className="flex items-start gap-2.5 text-sm text-slate-400">
                  <Phone size={15} className="mt-0.5 shrink-0 text-slate-500" /> {contact.phone}
                </li>
              )}
              {contact.whatsapp && (
                <li className="flex items-start gap-2.5 text-sm text-slate-400">
                  <MessageIcon className="mt-0.5 shrink-0 text-slate-500" /> {contact.whatsapp}
                </li>
              )}
              {contact.email && (
                <li className="flex items-start gap-2.5 text-sm text-slate-400 break-all">
                  <Mail size={15} className="mt-0.5 shrink-0 text-slate-500" /> {contact.email}
                </li>
              )}
            </ul>
          </div>

          {/* 4 — jam operasional */}
          <div className="space-y-3">
            <h3 className="text-sm font-semibold text-slate-100 uppercase tracking-wide">Jam Operasional</h3>
            <p className="flex items-start gap-2.5 text-sm text-slate-400">
              <Clock size={15} className="mt-0.5 shrink-0 text-slate-500" /> {contact.hours}
            </p>
          </div>
        </div>

        <div className="mt-12 pt-6 border-t border-slate-800">
          <p className="text-xs text-slate-500">&copy; {new Date().getFullYear()} {footerNote}</p>
        </div>
      </div>
    </footer>
  )
}

function MessageIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
    </svg>
  )
}

function FaviconSync() {
  const cms = useCms()
  useEffect(() => {
    if (cms.brand_favicon) {
      const existing = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
      if (existing) existing.href = cms.brand_favicon
      else {
        const link = document.createElement('link')
        link.rel = 'icon'
        link.href = cms.brand_favicon
        document.head.appendChild(link)
      }
    }
    if (cms.brand_name) document.title = cms.brand_name
  }, [cms.brand_favicon, cms.brand_name])
  return null
}

export const PublicLayout: React.FC = () => {
  useHashScroll()
  return (
    <CmsProvider>
      <FaviconSync />
      <div className="flex flex-col min-h-screen bg-slate-50">
        <Navbar />
        <main className="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <Outlet />
        </main>
        <BrandFooter />
      </div>
    </CmsProvider>
  )
}