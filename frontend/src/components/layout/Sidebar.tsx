import React from 'react'
import { Link, useLocation } from 'react-router-dom'
import { X } from 'lucide-react'
import { cn } from '@/utils/cn'

export interface SidebarItem {
  label: string
  href: string
  icon: React.ReactNode
}

export interface SidebarProps {
  items: SidebarItem[]
  title?: string
  isOpen?: boolean
  onClose?: () => void
}

export const Sidebar: React.FC<SidebarProps> = ({ items, title, isOpen = false, onClose }) => {
  const location = useLocation()

  const sidebarContent = (
    <div className="flex flex-col h-full bg-white">
      <div className="flex items-center justify-between p-4 border-b border-slate-100">
        {title && (
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
            {title}
          </span>
        )}
        {onClose && (
          <button
            onClick={onClose}
            aria-label="Tutup navigasi samping"
            className="md:hidden text-slate-400 hover:text-slate-600 p-1 rounded-lg cursor-pointer"
          >
            <X size={18} />
          </button>
        )}
      </div>
      <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
        {items.map((item) => {
          const isActive = location.pathname === item.href || location.pathname.startsWith(`${item.href}/`)
          return (
            <Link
              key={item.href}
              to={item.href}
              onClick={() => onClose && onClose()}
              className={cn(
                'flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors min-h-[44px]',
                isActive
                  ? 'bg-primary-50 text-primary-700 font-semibold'
                  : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
              )}
            >
              <span className={isActive ? 'text-primary-600' : 'text-slate-400'}>
                {item.icon}
              </span>
              {item.label}
            </Link>
          )
        })}
      </nav>
    </div>
  )

  return (
    <>
      {/* Desktop Persistent Sidebar */}
      <aside className="hidden md:flex w-64 border-r border-slate-200 bg-white flex-col shrink-0 min-h-[calc(100vh-4rem)]">
        {sidebarContent}
      </aside>

      {/* Mobile Off-Canvas Drawer */}
      {isOpen && (
        <div className="fixed inset-0 z-50 md:hidden flex">
          {/* Backdrop */}
          <div
            className="fixed inset-0 bg-slate-950/40 backdrop-blur-xs transition-opacity"
            onClick={onClose}
            aria-hidden="true"
          />

          {/* Drawer content */}
          <div className="relative w-64 max-w-[80vw] h-full shadow-2xl z-10 border-r border-slate-200">
            {sidebarContent}
          </div>
        </div>
      )}
    </>
  )
}
