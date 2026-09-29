import React, { useEffect, useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { LayoutDashboard, Truck, Sparkles, CalendarCheck, Receipt, User, MapPin, ShoppingCart, ClipboardList, Clock, Wallet, RotateCcw, Bell } from 'lucide-react'
import { Navbar } from './Navbar'
import { Sidebar } from './Sidebar'
import { notificationService } from '@/features/notification/services/notificationService'
import type { SidebarItem } from './Sidebar'

const NOTIFICATIONS_CHANGED_EVENT = 'rafa:notifications-changed'

export const UserLayout: React.FC = () => {
  const [isMobileOpen, setIsMobileOpen] = useState(false)
  const [unreadCount, setUnreadCount] = useState(0)
  const location = useLocation()

  // Unread badge: fetched on mount, on every navigation, and whenever the
  // notifications page marks items as read. No polling, no duplicate fetches.
  useEffect(() => {
    let mounted = true
    const refresh = () => {
      notificationService
        .unreadCount()
        .then((count) => {
          if (mounted) setUnreadCount(count)
        })
        .catch(() => {
          // Silent: keep the previous badge value on transient failures
        })
    }

    refresh()
    window.addEventListener(NOTIFICATIONS_CHANGED_EVENT, refresh)
    return () => {
      mounted = false
      window.removeEventListener(NOTIFICATIONS_CHANGED_EVENT, refresh)
    }
  }, [location.pathname])

  const navItems: SidebarItem[] = [
    { label: 'Overview', href: '/app', icon: <LayoutDashboard size={18} /> },
    { label: 'Katalog Alat', href: '/app/equipment', icon: <Truck size={18} /> },
    { label: 'Rekomendasi Alat', href: '/app/recommendations', icon: <Sparkles size={18} /> },
    { label: 'Keranjang Sewa', href: '/app/cart', icon: <ShoppingCart size={18} /> },
    { label: 'Lokasi Proyek', href: '/app/locations', icon: <MapPin size={18} /> },
    { label: 'Sewa Saya', href: '/app/bookings', icon: <CalendarCheck size={18} /> },
    { label: 'Rental Saya', href: '/app/rentals', icon: <ClipboardList size={18} /> },
    { label: 'Timesheet Harian', href: '/app/timesheets', icon: <Clock size={18} /> },
    { label: 'Tagihan & Bayar', href: '/app/invoices', icon: <Receipt size={18} /> },
    { label: 'Refund Saya', href: '/app/refunds', icon: <RotateCcw size={18} /> },
    { label: 'Outstanding', href: '/app/outstanding', icon: <Wallet size={18} /> },
    { label: 'Notifikasi', href: '/app/notifications', icon: <Bell size={18} />, badge: unreadCount },
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
