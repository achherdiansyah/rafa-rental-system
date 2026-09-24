import React from 'react'
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X } from 'lucide-react'
import { cn } from '@/utils/cn'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface ToastItem {
  id: string
  type: ToastType
  title?: string
  message: string
  duration?: number
}

export interface ToastProps {
  toast: ToastItem
  onDismiss: (id: string) => void
}

export const Toast: React.FC<ToastProps> = ({ toast, onDismiss }) => {
  const icons = {
    success: <CheckCircle2 className="text-emerald-500 shrink-0" size={20} />,
    error: <AlertCircle className="text-rose-500 shrink-0" size={20} />,
    warning: <AlertTriangle className="text-amber-500 shrink-0" size={20} />,
    info: <Info className="text-primary-500 shrink-0" size={20} />,
  }

  const borders = {
    success: 'border-emerald-200 bg-white shadow-lg',
    error: 'border-rose-200 bg-white shadow-lg',
    warning: 'border-amber-200 bg-white shadow-lg',
    info: 'border-primary-200 bg-white shadow-lg',
  }

  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border p-4 transition-all duration-200 ease-out',
        borders[toast.type]
      )}
    >
      {icons[toast.type]}
      <div className="flex-1 space-y-0.5">
        {toast.title && <p className="text-sm font-semibold text-slate-900">{toast.title}</p>}
        <p className="text-sm text-slate-600 leading-snug">{toast.message}</p>
      </div>
      <button
        onClick={() => onDismiss(toast.id)}
        aria-label="Tutup notifikasi"
        className="text-slate-400 hover:text-slate-600 p-0.5 rounded cursor-pointer"
      >
        <X size={16} />
      </button>
    </div>
  )
}
