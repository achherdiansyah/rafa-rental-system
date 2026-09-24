import React, { createContext, useState, useCallback } from 'react'
import { Toast } from '@/components/feedback/Toast'
import type { ToastItem, ToastType } from '@/components/feedback/Toast'

export interface ToastContextType {
  showToast: (message: string, type?: ToastType, title?: string, duration?: number) => void
  success: (message: string, title?: string) => void
  error: (message: string, title?: string) => void
  warning: (message: string, title?: string) => void
  info: (message: string, title?: string) => void
}

export const ToastContext = createContext<ToastContextType | undefined>(undefined)

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [toasts, setToasts] = useState<ToastItem[]>([])

  const dismissToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
  }, [])

  const showToast = useCallback(
    (message: string, type: ToastType = 'info', title?: string, duration: number = 4000) => {
      const id = Math.random().toString(36).substring(2, 9)
      const newToast: ToastItem = { id, message, type, title, duration }

      setToasts((prev) => [...prev, newToast])

      if (duration > 0) {
        setTimeout(() => {
          dismissToast(id)
        }, duration)
      }
    },
    [dismissToast]
  )

  const success = useCallback((message: string, title?: string) => showToast(message, 'success', title), [showToast])
  const error = useCallback((message: string, title?: string) => showToast(message, 'error', title), [showToast])
  const warning = useCallback((message: string, title?: string) => showToast(message, 'warning', title), [showToast])
  const info = useCallback((message: string, title?: string) => showToast(message, 'info', title), [showToast])

  return (
    <ToastContext.Provider value={{ showToast, success, error, warning, info }}>
      {children}
      {/* Toast Container */}
      <div
        aria-live="assertive"
        className="pointer-events-none fixed inset-0 z-50 flex flex-col items-end gap-2 p-4 sm:p-6 justify-start sm:justify-start"
      >
        {toasts.map((toast) => (
          <Toast key={toast.id} toast={toast} onDismiss={dismissToast} />
        ))}
      </div>
    </ToastContext.Provider>
  )
}
