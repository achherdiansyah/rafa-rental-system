import React, { useEffect } from 'react'
import { Link, Outlet } from 'react-router-dom'
import { HardHat } from 'lucide-react'
import { Navbar } from './Navbar'
import { CmsProvider, useCms } from '@/features/cms/CmsContext'
import { useHashScroll } from '@/hooks/useHashScroll'

const DEFAULT_NAV = [
  { label: 'Beranda', href: '/' },
  { label: 'Equipment', href: '/#equipment' },
  { label: 'Tentang Kami', href: '/#about' },
  { label: 'Kontak', href: '/#contact' },
]

function BrandFooter() {
  const cms = useCms()
  const brand = cms.brand_name || 'CV SUMBER MAKMUR RAFA'
  const brandLogo = cms.brand_logo || null
  const footerNote = cms.footer || 'PT RAFA Rental Nusantara. All rights reserved.'
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
    <footer className="bg-slate-950 text-slate-400">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 space-y-8">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-600 text-slate-950 overflow-hidden">
              {brandLogo ? <img src={brandLogo} alt={brand} className="w-full h-full object-contain p-1 bg-white" /> : <HardHat size={20} />}
            </span>
            <span className="font-bold text-lg text-accent-500">{brand}</span>
          </div>
          <nav aria-label="Navigasi footer" className="flex flex-wrap gap-6">
            {nav.map((m) => (
              <Link key={m.href} to={m.href} className="text-sm text-slate-400 hover:text-accent-400 transition-colors">
                {m.label}
              </Link>
            ))}
          </nav>
        </div>
        <p className="text-xs text-slate-500 border-t border-slate-800 pt-6">
          &copy; {new Date().getFullYear()} {footerNote}
        </p>
      </div>
    </footer>
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