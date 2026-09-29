import React, { useEffect } from 'react'
import { Outlet } from 'react-router-dom'
import { Navbar } from './Navbar'
import { CmsProvider, useCms } from '@/features/cms/CmsContext'
import { useHashScroll } from '@/hooks/useHashScroll'

function BrandFooter() {
  const cms = useCms()
  const brand = cms.brand_name || 'PT RAFA Rental Nusantara'
  return (
    <footer className="border-t border-slate-200 py-8 bg-white text-center text-sm text-slate-500">
      &copy; {new Date().getFullYear()} {brand}. All rights reserved.
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