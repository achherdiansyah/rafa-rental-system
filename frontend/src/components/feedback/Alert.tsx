import React from 'react'
import { AlertCircle, CheckCircle2, Info, AlertTriangle, X } from 'lucide-react'
import { cn } from '@/utils/cn'

export interface AlertProps {
  variant?: 'info' | 'success' | 'warning' | 'danger'
  title?: string
  children: React.ReactNode
  onClose?: () => void
  className?: string
}

export const Alert: React.FC<AlertProps> = ({
  variant = 'info',
  title,
  children,
  onClose,
  className,
}) => {
  const icons = {
    info: <Info className="text-primary-600 shrink-0" size={20} />,
    success: <CheckCircle2 className="text-emerald-600 shrink-0" size={20} />,
    warning: <AlertTriangle className="text-amber-600 shrink-0" size={20} />,
    danger: <AlertCircle className="text-rose-600 shrink-0" size={20} />,
  }

  const styles = {
    info: 'bg-primary-50/70 border-primary-200 text-primary-900',
    success: 'bg-emerald-50/70 border-emerald-200 text-emerald-900',
    warning: 'bg-amber-50/70 border-amber-200 text-amber-900',
    danger: 'bg-rose-50/70 border-rose-200 text-rose-900',
  }

  return (
    <div
      role="alert"
      className={cn('relative flex gap-3 p-4 rounded-xl border text-sm', styles[variant], className)}
    >
      {icons[variant]}
      <div className="flex-1 space-y-0.5">
        {title && <h5 className="font-semibold leading-none tracking-tight">{title}</h5>}
        <div className="text-slate-600 leading-relaxed">{children}</div>
      </div>
      {onClose && (
        <button
          onClick={onClose}
          aria-label="Dismiss alert"
          className="text-slate-400 hover:text-slate-600 p-0.5 rounded cursor-pointer"
        >
          <X size={16} />
        </button>
      )}
    </div>
  )
}
