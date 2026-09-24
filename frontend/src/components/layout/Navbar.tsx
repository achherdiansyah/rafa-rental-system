import React from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { HardHat, LogOut, User, Menu } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'

export interface NavbarProps {
  onMenuToggle?: () => void
  showMenuToggle?: boolean
}

export const Navbar: React.FC<NavbarProps> = ({ onMenuToggle, showMenuToggle = false }) => {
  const { user, isAuthenticated, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  return (
    <header className="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/95 backdrop-blur-xs">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div className="flex items-center gap-3">
          {showMenuToggle && onMenuToggle && (
            <button
              onClick={onMenuToggle}
              aria-label="Buka menu navigasi"
              className="md:hidden text-slate-500 hover:text-slate-700 p-1 -ml-1 rounded-md cursor-pointer"
            >
              <Menu size={24} />
            </button>
          )}
          <Link to="/" className="flex items-center gap-2.5 font-bold text-lg text-slate-900">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 text-white shadow-xs">
              <HardHat size={20} />
            </div>
            <span className="hidden sm:inline">RAFA Rental</span>
          </Link>
        </div>

        <nav className="flex items-center gap-2 sm:gap-4">
          {isAuthenticated && user ? (
            <div className="flex items-center gap-2 sm:gap-3">
              <div className="flex items-center gap-2 text-sm text-slate-700">
                <User size={16} className="text-slate-400 hidden sm:block" />
                <span className="font-medium hidden lg:inline">{user.name}</span>
                <Badge variant={user.role === 'OWNER' ? 'warning' : user.role === 'ADMIN' ? 'success' : 'secondary'} size="sm">
                  {user.role}
                </Badge>
              </div>

              <div className="hidden sm:flex items-center gap-1">
                {user.role === 'USER' && (
                  <Link to="/app">
                    <Button variant="ghost" size="sm">Portal User</Button>
                  </Link>
                )}
                {user.role === 'ADMIN' && (
                  <Link to="/admin">
                    <Button variant="ghost" size="sm">Admin Desk</Button>
                  </Link>
                )}
                {user.role === 'OWNER' && (
                  <Link to="/owner">
                    <Button variant="ghost" size="sm">Owner Exec</Button>
                  </Link>
                )}
              </div>

              <Button variant="outline" size="sm" onClick={handleLogout} className="px-2.5 sm:px-4">
                <LogOut size={16} className="sm:mr-1.5" />
                <span className="hidden sm:inline">Logout</span>
              </Button>
            </div>
          ) : (
            <div className="flex items-center gap-2">
              <Link to="/login">
                <Button variant="ghost" size="sm">Login</Button>
              </Link>
              <Link to="/register">
                <Button variant="primary" size="sm">Register</Button>
              </Link>
            </div>
          )}
        </nav>
      </div>
    </header>
  )
}
