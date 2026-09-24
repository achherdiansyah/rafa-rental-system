import React, { useState } from 'react'
import { Outlet } from 'react-router-dom'
import { LayoutDashboard, TrendingUp, DollarSign, History, Settings } from 'lucide-react'
import { Navbar } from './Navbar'
import { Sidebar } from './Sidebar'
import type { SidebarItem } from './Sidebar'

export const OwnerLayout: React.FC = () => {
  const [isMobileOpen, setIsMobileOpen] = useState(false)

  const navItems: SidebarItem[] = [
    { label: 'Executive Summary', href: '/owner', icon: <LayoutDashboard size={18} /> },
    { label: 'Laporan Pendapatan', href: '/owner/revenue', icon: <TrendingUp size={18} /> },
    { label: 'Master Tarif & Harga', href: '/owner/pricing', icon: <DollarSign size={18} /> },
    { label: 'Audit Trail Logs', href: '/owner/audit', icon: <History size={18} /> },
    { label: 'Rekening & Kontrol', href: '/owner/settings', icon: <Settings size={18} /> },
  ]

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      <Navbar showMenuToggle onMenuToggle={() => setIsMobileOpen((prev) => !prev)} />
      <div className="flex flex-1">
        <Sidebar
          items={navItems}
          title="Owner Governance"
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
