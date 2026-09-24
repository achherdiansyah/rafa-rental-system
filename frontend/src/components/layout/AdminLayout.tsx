import React, { useState } from 'react'
import { Outlet } from 'react-router-dom'
import { LayoutDashboard, CheckSquare, Layers, Truck, Clock, CreditCard, RefreshCw } from 'lucide-react'
import { Navbar } from './Navbar'
import { Sidebar } from './Sidebar'
import type { SidebarItem } from './Sidebar'

export const AdminLayout: React.FC = () => {
  const [isMobileOpen, setIsMobileOpen] = useState(false)

  const navItems: SidebarItem[] = [
    { label: 'Admin Desk', href: '/admin', icon: <LayoutDashboard size={18} /> },
    { label: 'Master Armada & Tipe', href: '/admin/equipment', icon: <Layers size={18} /> },
    { label: 'Approval Booking', href: '/admin/bookings', icon: <CheckSquare size={18} /> },
    { label: 'Alokasi Unit Fisik', href: '/admin/units', icon: <Truck size={18} /> },
    { label: 'Validasi Timesheet', href: '/admin/timesheets', icon: <Clock size={18} /> },
    { label: 'Verifikasi Pembayaran', href: '/admin/payments', icon: <CreditCard size={18} /> },
    { label: 'Eksekusi Refund', href: '/admin/refunds', icon: <RefreshCw size={18} /> },
  ]

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      <Navbar showMenuToggle onMenuToggle={() => setIsMobileOpen((prev) => !prev)} />
      <div className="flex flex-1">
        <Sidebar
          items={navItems}
          title="Operasional Lapangan"
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
