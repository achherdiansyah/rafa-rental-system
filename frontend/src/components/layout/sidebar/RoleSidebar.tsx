import React, { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { ChevronDown, ChevronsLeft, ChevronsRight, LogOut, X, HardHat } from 'lucide-react'
import { cn } from '@/utils/cn'
import { useAuth } from '@/hooks/useAuth'
import { useCms } from '@/features/cms/CmsContext'
import { useNavigate } from 'react-router-dom'

export interface SidebarItem {
  label: string
  href: string
  icon: React.ReactNode
  badge?: number
}

export interface SidebarGroup {
  title: string
  items: SidebarItem[]
}

interface RoleSidebarProps {
  groups: SidebarGroup[]
  title?: string
  isOpen?: boolean
  onClose?: () => void
  /** true = mobile drawer mode (also used for desktop expanded) */
}

const isActive = (pathname: string, href: string): boolean => {
  if (href === '/') return pathname === '/'
  if (pathname === href) return true
  if (href !== '/' && pathname.startsWith(href + '/')) return true
  // exact dashboard roots: keep '/' home only matched exactly
  return href === '/app' || href === '/admin' || href === '/owner' ? pathname === href : false
}

function MenuItem({
  item,
  collapsed,
  onClick,
}: {
  item: SidebarItem
  collapsed: boolean
  onClick?: () => void
}) {
  const { pathname } = useLocation()
  const active = isActive(pathname, item.href)

  const inner = (
    <Link
      to={item.href}
      onClick={onClick}
      title={collapsed ? item.label : undefined}
      className={cn(
        'flex items-center gap-3 rounded-md text-sm font-medium h-[42px] px-3 transition-colors',
        active ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
      )}
    >
      {active && <span className="absolute left-0 top-1/2 -translate-y-1/2 h-6 w-[3px] rounded-r bg-primary-600" />}
      <span className={cn('relative flex items-center justify-center shrink-0', active ? 'text-primary-600' : 'text-slate-400')}>
        {item.icon}
      </span>
      {!collapsed && <span className="truncate">{item.label}</span>}
      {!collapsed && typeof item.badge === 'number' && item.badge > 0 && (
        <span className="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-bold text-white bg-rose-500">
          {item.badge > 99 ? '99+' : item.badge}
        </span>
      )}
    </Link>
  )

  return <div className="relative">{inner}</div>
}

function MenuGroup({
  group,
  collapsed,
  onNavigate,
}: {
  group: SidebarGroup
  collapsed: boolean
  onNavigate?: () => void
}) {
  const { pathname } = useLocation()
  const anyActive = group.items.some((i) => isActive(pathname, i.href))
  const [open, setOpen] = useState(true)

  useEffect(() => {
    if (anyActive) setOpen(true)
  }, [anyActive])

  return (
    <div className="space-y-1">
      <button
        type="button"
        onClick={() => !collapsed && setOpen((o) => !o)}
        className="flex w-full items-center justify-between px-3 pt-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400 cursor-pointer select-none"
        aria-expanded={open}
      >
        {collapsed ? (
          <span className="mx-auto text-slate-300">•</span>
        ) : (
          <>
            <span>{group.title}</span>
            <ChevronDown size={14} className={cn('transition-transform', open ? '' : '-rotate-90 text-slate-300')} />
          </>
        )}
      </button>
      {(!collapsed && open) || collapsed ? (
        <div className="space-y-1">
          {group.items.map((item) => (
            <MenuItem key={item.href + item.label} item={item} collapsed={collapsed} onClick={onNavigate} />
          ))}
        </div>
      ) : null}
    </div>
  )
}

export const RoleSidebar: React.FC<RoleSidebarProps> = ({ groups, title = 'Portal', isOpen = false, onClose }) => {
  const { user, logout } = useAuth()
  const cms = useCms()
  const navigate = useNavigate()
  const [collapsed, setCollapsed] = useState(false)

  const brand = user?.role === 'ADMIN' ? 'Admin' : user?.role === 'OWNER' ? 'Owner' : 'Customer'
  const brandName = cms.brand_name || 'RAFA Rental'
  const brandLogo = cms.brand_logo || null

  const handleLogout = () => {
    logout()
    navigate('/login')
    onClose?.()
  }

  const body = (
    <div className="flex h-full flex-col bg-white border-r border-slate-200">
      {/* Brand */}
      <div className={cn('relative flex items-center gap-2.5 px-4 h-16 shrink-0', collapsed ? 'justify-center' : '')}>
        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 text-white overflow-hidden shrink-0">
          {brandLogo ? <img src={brandLogo} alt={brandName} className="w-full h-full object-contain p-1 bg-white" /> : <HardHat size={20} />}
        </span>
        {!collapsed && (
          <div className="min-w-0">
            <p className="font-bold text-slate-900 leading-tight truncate">{brandName}</p>
            <p className="text-[11px] text-slate-400">{title} · {brand}</p>
          </div>
        )}
        {onClose && (
          <button onClick={onClose} aria-label="Tutup navigasi" className="md:hidden ml-auto text-slate-400 hover:text-slate-600 rounded-md p-1 cursor-pointer">
            <X size={18} />
          </button>
        )}
        {/* Desktop collapse toggle — icon only, at the top */}
        <button
          type="button"
          onClick={() => setCollapsed((c) => !c)}
          aria-label={collapsed ? 'Perluas menu' : 'Ciutkan menu'}
          title={collapsed ? 'Perluas menu' : 'Ciutkan menu'}
          className="hidden md:flex items-center justify-center text-slate-400 hover:text-slate-700 rounded-md p-1.5 cursor-pointer ml-auto"
        >
          {collapsed ? <ChevronsRight size={18} /> : <ChevronsLeft size={18} />}
        </button>
      </div>

      {/* Menu area */}
      <nav aria-label={`Menu ${title}`} className="flex-1 overflow-y-auto px-2 pb-2 [scrollbar-width:thin]">
        {collapsed ? (
          <div className="space-y-1 pt-4">
            {groups.flatMap((g) => g.items).map((item) => (
              <MenuItem key={item.href + item.label} item={item} collapsed onClick={undefined} />
            ))}
          </div>
        ) : (
          groups.map((g) => <MenuGroup key={g.title} group={g} collapsed={false} />)
        )}
      </nav>

      {/* Footer: profile + logout */}
      <div className="shrink-0 border-t border-slate-100 p-3 space-y-1">
        <div className={cn('flex items-center gap-3 px-3 h-[42px]', collapsed ? 'justify-center' : '')}>
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 text-sm font-semibold shrink-0">
            {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
          </span>
          {!collapsed && (
            <div className="min-w-0">
              <p className="text-sm font-medium text-slate-800 truncate">{user?.name}</p>
              <p className="text-[11px] text-slate-400">{user?.role}</p>
            </div>
          )}
        </div>
        <button
          type="button"
          onClick={handleLogout}
          title={collapsed ? 'Keluar' : undefined}
          aria-label="Keluar"
          className={cn(
            'flex items-center gap-3 rounded-md text-sm font-medium h-[42px] px-3 w-full text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer',
            collapsed ? 'justify-center' : ''
          )}
        >
          <LogOut size={19} className="shrink-0" />
          {!collapsed && <span>Keluar</span>}
        </button>
      </div>
    </div>
  )

  return (
    <>
      {/* Desktop */}
      <aside className={cn('hidden md:block shrink-0 sticky top-20 h-[calc(100vh-5rem)] transition-[width] duration-200', collapsed ? 'w-[72px]' : 'w-[256px]')}>
        {body}
      </aside>

      {/* Mobile drawer */}
      {isOpen && (
        <div className="fixed inset-0 z-50 md:hidden flex">
          <div className="fixed inset-0 bg-slate-950/40" onClick={onClose} aria-hidden="true" />
          <div className="relative w-64 max-w-[80vw] h-full shadow-2xl">
            {body}
          </div>
        </div>
      )}
    </>
  )
}
export default RoleSidebar