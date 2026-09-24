import React from 'react'
import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'

export const GuestRoute: React.FC = () => {
  const { isAuthenticated, isChecking, user } = useAuth()

  if (isChecking) return null

  if (isAuthenticated && user) {
    if (user.role === 'ADMIN') return <Navigate to="/admin" replace />
    if (user.role === 'OWNER') return <Navigate to="/owner" replace />
    return <Navigate to="/app" replace />
  }

  return <Outlet />
}
