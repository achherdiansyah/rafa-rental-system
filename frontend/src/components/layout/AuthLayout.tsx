import React from 'react'
import { Link, Outlet } from 'react-router-dom'
import { HardHat } from 'lucide-react'
import { CmsProvider, useCms } from '@/features/cms/CmsContext'

function HeaderBrand() {
  const cms = useCms()
  const brand = cms.brand_name || 'CV SUMBER MAKMUR RAFA'
  const logo = cms.brand_logo || null
  return (
    <Link to="/" className="inline-flex items-center gap-2.5 font-bold text-2xl text-slate-900">
      <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-600 text-white overflow-hidden">
        {logo ? <img src={logo} alt={brand} className="w-full h-full object-contain p-1 bg-white" /> : <HardHat size={24} />}
      </span>
      <span>{brand}</span>
    </Link>
  )
}

export const AuthLayout: React.FC = () => {
  return (
    <CmsProvider>
      <AuthShell />
    </CmsProvider>
  )
}

function AuthShell() {
  const cms = useCms()
  const heroImage = cms.hero_image || '/hero-equipment.svg'

  return (
    <div className="min-h-screen grid grid-cols-1 lg:grid-cols-2 bg-white">
      {/* LEFT — visual panel (desktop only) */}
      <div className="hidden lg:flex flex-col justify-center p-8 lg:p-12">
        <div className="relative h-full min-h-[70vh] rounded-r-2xl overflow-hidden">
          <img src={heroImage} alt="Armada alat berat" className="absolute inset-0 w-full h-full object-cover object-right" />
          <div className="absolute inset-0 bg-black/35" aria-hidden="true" />
        </div>
      </div>

      {/* RIGHT — form (desktop right / centered card on tablet & mobile) */}
      <div className="flex items-center justify-center bg-slate-50 lg:bg-white px-4 py-12 sm:px-6">
        <div className="w-full max-w-md">
          <div className="text-center mb-8">
            <HeaderBrand />
          </div>
          <div className="bg-white py-8 px-6 shadow-sm border border-slate-200 rounded-2xl sm:px-10">
            <Outlet />
          </div>
        </div>
      </div>
    </div>
  )
}
export default AuthLayout