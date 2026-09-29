import React, { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { HardHat, LogOut, User, Menu, X } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { useCms } from '@/features/cms/CmsContext'
import { useLocation } from 'react-router-dom'
import { scrollToElementId } from '@/hooks/useHashScroll'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { cn } from '@/utils/cn'

export interface NavbarProps {
  onMenuToggle?: () => void
  showMenuToggle?: boolean
}

const DEFAULT_MENU: Array<{ label: string; href: string }> = [
  { label: 'Beranda', href: '/#home' },
  { label: 'Equipment', href: '/#equipment' },
  { label: 'Tentang Kami', href: '/#about' },
  { label: 'Kontak', href: '/#contact' },
]

const isCurrentHash = (pathname: string, hash: string, href: string): boolean => {
  const target = href.split('#')[1] ?? 'home'
  if (pathname !== '/') return false
  const current = hash.replace('#', '')
  if (target === 'home') return current === '' || current === 'home'
  return current === target
}

export const Navbar: React.FC<NavbarProps> = ({ onMenuToggle, showMenuToggle = false }) => {
  const { user, isAuthenticated, logout } = useAuth()
  const cms = useCms()
  const navigate = useNavigate()
  const location = useLocation()
  const [mobileOpen, setMobileOpen] = useState(false)
  const mobileRef = useRef<HTMLDivElement>(null)

  const brandName = cms.brand_name || 'CV SUMBER MAKMUR RAFA'
  const brandLogo = cms.brand_logo || null
  let cmsMenu: Array<{ label: string; href: string }> = []
  try {
    const parsed = cms.navbar ? JSON.parse(cms.navbar) : []
    if (Array.isArray(parsed)) {
      cmsMenu = parsed.filter(
        (m: { label?: unknown; href?: unknown }) => m && typeof m.label === 'string' && typeof m.href === 'string'
      ) as Array<{ label: string; href: string }>
    }
  } catch {
    cmsMenu = []
  }
  const menu = cmsMenu.length > 0 ? cmsMenu : DEFAULT_MENU

  const go = (href: string) => {
    setMobileOpen(false)
    const [path, hash] = href.split('#')
    if (path && path !== location.pathname) {
      navigate(href)
      return
    }
    if (hash) {
      // keep the URL hash in sync (refresh-safe + active-state) then scroll
      if (window.location.hash !== `#${hash}`) {
        window.location.hash = hash
      }
      scrollToElementId(hash)
    } else if (href === '/') {
      navigate('/')
    }
  }

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setMobileOpen(false)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  useEffect(() => {
    if (!mobileOpen) return
    const onPointer = (e: MouseEvent) => {
      if (mobileRef.current && !mobileRef.current.contains(e.target as Node)) setMobileOpen(false)
    }
    document.addEventListener('mousedown', onPointer)
    return () => document.removeEventListener('mousedown', onPointer)
  }, [mobileOpen])

  const handleLogout = () => {
    setMobileOpen(false)
    logout()
    navigate('/login')
  }

  return (
    <header className="sticky top-0 z-40 w-full border-b border-slate-200 bg-white shadow-sm">
      <div className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        {/* LEFT: logo */}
        <div className="flex items-center gap-3 min-w-0">
          {showMenuToggle && onMenuToggle && (
            <button
              onClick={onMenuToggle}
              aria-label="Buka menu navigasi"
              className="md:hidden text-slate-500 hover:text-slate-700 p-1 -ml-1 rounded-md cursor-pointer"
            >
              <Menu size={24} />
            </button>
          )}
          <Link to="/" className="flex items-center gap-2.5 font-bold text-lg text-slate-900 min-w-0" onClick={() => setMobileOpen(false)}>
            <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-600 text-white shadow-btn overflow-hidden shrink-0">
              {brandLogo ? <img src={brandLogo} alt={brandName} className="w-full h-full object-contain p-1 bg-white" /> : <HardHat size={22} />}
            </span>
            <span className="hidden sm:inline truncate max-w-[200px]">{brandName}</span>
          </Link>
        </div>

        {/* CENTER: menu (desktop) */}
        <nav aria-label="Navigasi utama" className="hidden lg:flex items-center gap-6 xl:gap-8">
          {menu.map((m) => {
            const active = isCurrentHash(location.pathname, location.hash, m.href)
            return (
              <button
                key={m.href}
                type="button"
                onClick={() => go(m.href)}
                className={cn(
                  'text-[15px] font-medium transition-colors cursor-pointer py-2 border-b-2 -mb-0.5 hover:text-slate-900',
                  active ? 'text-accent-600 border-accent-500' : 'text-slate-600 border-transparent hover:text-slate-900'
                )}
              >
                {m.label}
              </button>
            )
          })}
        </nav>

        {/* RIGHT: actions */}
        <div className="flex items-center gap-2 shrink-0">
          {isAuthenticated && user ? (
            <>
              <span className="hidden md:flex items-center gap-2 text-sm text-slate-700">
                <User size={16} className="text-slate-400" />
                <span className="font-medium">{user.name}</span>
                <Badge variant={user.role === 'OWNER' ? 'warning' : user.role === 'ADMIN' ? 'success' : 'secondary'} size="sm">
                  {user.role}
                </Badge>
              </span>
              <div className="hidden sm:flex items-center gap-1">
                {user.role === 'USER' && (
                  <Link to="/app" onClick={() => setMobileOpen(false)}>
                    <Button variant="ghost" size="sm">Portal User</Button>
                  </Link>
                )}
                {user.role === 'ADMIN' && (
                  <Link to="/admin" onClick={() => setMobileOpen(false)}>
                    <Button variant="ghost" size="sm">Admin Desk</Button>
                  </Link>
                )}
                {user.role === 'OWNER' && (
                  <Link to="/owner" onClick={() => setMobileOpen(false)}>
                    <Button variant="ghost" size="sm">Owner Exec</Button>
                  </Link>
                )}
              </div>
              <Button variant="outline" size="sm" onClick={handleLogout}>
                <LogOut size={16} className="sm:mr-1.5" />
                <span className="hidden sm:inline">Logout</span>
              </Button>
            </>
          ) : (
            <>
              <Link to="/register">
                <Button variant="outline" size="sm" className="h-9 px-4 text-[15px] font-medium rounded-lg">
                  Daftar
                </Button>
              </Link>
              <Link to="/login">
                <Button variant="accent" size="sm" className="h-9 px-4 text-[15px] font-medium rounded-lg">
                  Masuk
                </Button>
              </Link>
              <button
                type="button"
                onClick={() => setMobileOpen((v) => !v)}
                aria-label={mobileOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'}
                aria-expanded={mobileOpen}
                className="lg:hidden text-slate-600 hover:text-slate-900 p-1 rounded-md cursor-pointer"
              >
                {mobileOpen ? <X size={24} /> : <Menu size={24} />}
              </button>
            </>
          )}
        </div>
      </div>

      {/* MOBILE panel */}
      {mobileOpen && (
        <div ref={mobileRef} className="lg:hidden border-t border-slate-100 bg-white">
          <nav aria-label="Navigasi mobile" className="px-4 py-3 space-y-1">
            {menu.map((m) => (
              <button
                key={m.href}
                type="button"
                onClick={() => go(m.href)}
                className={cn(
                  'block w-full text-left px-3 py-2.5 rounded-lg font-medium transition-colors cursor-pointer',
                  isCurrentHash(location.pathname, location.hash, m.href)
                    ? 'bg-accent-50 text-accent-700'
                    : 'text-slate-700 hover:bg-slate-50'
                )}
              >
                {m.label}
              </button>
            ))}
          </nav>
        </div>
      )}
    </header>
  )
}
export default Navbar