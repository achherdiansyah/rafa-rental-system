import React, { useState } from 'react'
import { Outlet } from 'react-router-dom'
import { LayoutDashboard, Truck, Sparkles, CalendarCheck, Receipt, User } from 'lucide-react'
import { Navbar } from './Navbar'
import { Sidebar } from './Sidebar'
import type { SidebarItem } from './Sidebar'

export const UserLayout: React.FC = () => {
  const [isMobileOpen, setIsMobileOpen] = useState(false)

  const navItems: SidebarItem[] = [
    { label: 'Overview', href: '/app', icon: <LayoutDashboard size={18} /> },
    { label: 'Katalog Alat', href: '/app/equipment', icon: <Truck size={18} /> },
    { label: 'Rekomendasi Alat', href: '/app/recommendations', icon: <Sparkles size={18} /> },
    { label: 'Sewa Saya', href: '/app/bookings', icon: <CalendarCheck size={18} /> },
    { label: 'Tagihan & Bayar', href: '/app/invoices', icon: <Receipt size={18} /> },
    { label: 'Profil Saya', href: '/app/profile', icon: <User size={18} /> },
  ]

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      <Navbar showMenuToggle onMenuToggle={() => setIsMobileOpen((prev) => !prev)} />
      <div className="flex flex-1">
        <Sidebar
          items={navItems}
          title="Customer Portal"
          isOpen={isMobileOpen}
          onClose={() => setIsMobileOpen(false)}
        />
        <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-6xl w-full">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
