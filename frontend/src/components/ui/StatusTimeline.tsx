import React from 'react'
import { CheckCircle2 } from 'lucide-react'
import { cn } from '@/utils/cn'

export interface TimelineStep {
  key: string
  label: string
  state: 'done' | 'current' | 'upcoming' | 'hidden'
}

/**
 * Lightweight horizontal status timeline (e.g. rental lifecycle). Presentation
 * only — business state machine unchanged.
 */
export const StatusTimeline: React.FC<{ steps: TimelineStep[]; className?: string }> = ({ steps, className }) => {
  const visible = steps.filter((s) => s.state !== 'hidden')
  if (visible.length === 0) return null
  const currentIdx = visible.findIndex((s) => s.state === 'current')

  return (
    <div className={cn('flex items-center', className)}>
      {visible.map((step, i) => {
        const isCurrent = step.state === 'current'
        const isDone = step.state === 'done'
        const passedBeforeCurrent = currentIdx === -1 || i <= currentIdx
        return (
          <React.Fragment key={step.key}>
            <div className="flex flex-col items-center gap-1 w-20">
              <div
                className={cn(
                  'w-7 h-7 rounded-full flex items-center justify-center border-2 transition-colors',
                  isDone && 'border-emerald-500 bg-emerald-50 text-emerald-600',
                  isCurrent && 'border-primary-600 bg-primary-50 text-primary-600',
                  !isDone && !isCurrent && 'border-slate-200 bg-white text-slate-300'
                )}
              >
                {isDone ? <CheckCircle2 size={16} /> : <span className="text-xs font-bold">{i + 1}</span>}
              </div>
              <span
                className={cn(
                  'text-[11px] leading-tight text-center',
                  isCurrent ? 'text-primary-700 font-semibold' : isDone ? 'text-slate-600' : 'text-slate-400'
                )}
              >
                {step.label}
              </span>
            </div>
            {i < visible.length - 1 && (
              <div className={cn('flex-1 h-0.5 rounded-full -mt-5', passedBeforeCurrent ? 'bg-emerald-300' : 'bg-slate-200')} />
            )}
          </React.Fragment>
        )
      })}
    </div>
  )
}
export default StatusTimeline