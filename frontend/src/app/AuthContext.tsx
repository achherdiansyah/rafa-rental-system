import React, { createContext, useState, useEffect, useCallback } from 'react'
import { authService } from '@/features/auth/services/authService'
import type {
  AuthContextType,
  AuthStatus,
  AuthUser,
  LoginCredentials,
  RegisterData,
} from '@/types/auth'

export const AuthContext = createContext<AuthContextType | undefined>(undefined)

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('rafa_token'))
  const [status, setStatus] = useState<AuthStatus>('checking')

  const setAuthSession = (newToken: string, newUser: AuthUser) => {
    localStorage.setItem('rafa_token', newToken)
    localStorage.setItem('rafa_user', JSON.stringify(newUser))
    setToken(newToken)
    setUser(newUser)
    setStatus('authenticated')
  }

  const clearAuthSession = () => {
    localStorage.removeItem('rafa_token')
    localStorage.removeItem('rafa_user')
    setToken(null)
    setUser(null)
    setStatus('unauthenticated')
  }

  const refreshUser = useCallback(async (): Promise<AuthUser | null> => {
    const currentToken = localStorage.getItem('rafa_token')
    if (!currentToken) {
      clearAuthSession()
      return null
    }

    try {
      const freshUser = await authService.getMe()
      setUser(freshUser)
      setStatus('authenticated')
      return freshUser
    } catch {
      clearAuthSession()
      return null
    }
  }, [])

  // Initial check on application boot
  useEffect(() => {
    const initAuth = async () => {
      const savedToken = localStorage.getItem('rafa_token')
      if (savedToken) {
        await refreshUser()
      } else {
        setStatus('unauthenticated')
      }
    }

    initAuth()
  }, [refreshUser])

  const login = async (credentials: LoginCredentials): Promise<AuthUser> => {
    const response = await authService.login(credentials)
    setAuthSession(response.token, response.user)
    return response.user
  }

  const register = async (data: RegisterData): Promise<AuthUser> => {
    const response = await authService.register(data)
    setAuthSession(response.token, response.user)
    return response.user
  }

  const logout = async (): Promise<void> => {
    try {
      if (token) {
        await authService.logout()
      }
    } catch {
      // Ignore network errors during logout and clear local state anyway
    } finally {
      clearAuthSession()
    }
  }

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        status,
        isAuthenticated: status === 'authenticated' && !!user,
        isChecking: status === 'checking',
        login,
        register,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}
