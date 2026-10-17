import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthContext } from '@/app/AuthContext'
import { RoleRoute } from './RoleRoute'
import type { AuthContextType, AuthUser, UserRole } from '@/types/auth'

const renderWithUser = (role: UserRole, allowedRoles: UserRole[]) => {
  const user: AuthUser = {
    id: 1,
    name: 'Test',
    email: 'test@example.com',
    role,
    phone_number: null,
    is_active: true,
  }

  const value = {
    user,
    token: 'token',
    status: 'authenticated',
    isAuthenticated: true,
    isChecking: false,
    login: async () => user,
    register: async () => user,
    logout: async () => undefined,
    refreshUser: async () => user,
  } as AuthContextType

  return render(
    <AuthContext.Provider value={value}>
      <MemoryRouter initialEntries={['/protected']}>
        <Routes>
          <Route element={<RoleRoute allowedRoles={allowedRoles} />}>
            <Route path="/protected" element={<div>PROTECTED CONTENT</div>} />
          </Route>
          <Route path="/forbidden" element={<div>FORBIDDEN PAGE</div>} />
        </Routes>
      </MemoryRouter>
    </AuthContext.Provider>
  )
}

describe('RoleRoute access control for new roles', () => {
  it('allows SUPERVISOR into the supervisor portal', () => {
    renderWithUser('SUPERVISOR', ['SUPERVISOR'])
    expect(screen.getByText('PROTECTED CONTENT')).toBeInTheDocument()
  })

  it('allows OPERATOR into the operator portal', () => {
    renderWithUser('OPERATOR', ['OPERATOR'])
    expect(screen.getByText('PROTECTED CONTENT')).toBeInTheDocument()
  })

  it('blocks USER from the supervisor portal', () => {
    renderWithUser('USER', ['SUPERVISOR'])
    expect(screen.getByText('FORBIDDEN PAGE')).toBeInTheDocument()
  })

  it('blocks SUPERVISOR from the operator portal', () => {
    renderWithUser('SUPERVISOR', ['OPERATOR'])
    expect(screen.getByText('FORBIDDEN PAGE')).toBeInTheDocument()
  })
})
