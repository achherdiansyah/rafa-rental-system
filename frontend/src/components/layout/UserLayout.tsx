import React, { useEffect, useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { Navbar } from './Navbar'
import { RoleSidebar } from './sidebar/RoleSidebar'
import { userMenu } from './sidebarNavigation'
import { notificationService } from '@/features/notification/services/notificationService'

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

  const navMenu = userMenu.map((g) =>
    g.title === 'Lainnya'
      ? { ...g, items: g.items.map((it) => (it.href.endsWith('notifications') ? { ...it, badge: unreadCount } : it)) }
      : g
  )

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col" data-role="user">
      <Navbar showMenuToggle hideMenu onMenuToggle={() => setIsMobileOpen((prev) => !prev)} />
      <div className="flex flex-1">
        <RoleSidebar
          groups={navMenu}
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
