import React from 'react'
import { cn } from '@/utils/cn'

export interface KpiCardProps {
  label: string
  value: string
  sub?: string
  icon?: React.ReactNode
  tone?: 'default' | 'primary' | 'success' | 'warning' | 'danger'
  className?: string
}

const TONES = {
  default: 'text-slate-900',
  primary: 'text-primary-700',
  success: 'text-emerald-600',
  warning: 'text-amber-600',
  danger: 'text-rose-600',
}

export const KpiCard: React.FC<KpiCardProps> = ({ label, value, sub, icon, tone = 'default', className }) => {
  return (
    <div className={cn('card-surface p-5', 'hoverable', className)}>
      <div className="flex items-center justify-between mb-3">
        <p className="text-sm font-medium text-slate-500">{label}</p>
        {icon && <span className="text-slate-300">{icon}</span>}
      </div>
      <p className={cn('font-mono font-extrabold text-2xl tracking-tight', TONES[tone])}>{value}</p>
      {sub && <p className="text-xs text-slate-400 mt-1.5">{sub}</p>}
    </div>
  )
}
export default KpiCard