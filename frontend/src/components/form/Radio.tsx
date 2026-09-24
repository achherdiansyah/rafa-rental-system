import React, { useId } from 'react'
import { cn } from '@/utils/cn'

export interface RadioProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string
  description?: string
}

export const Radio = React.forwardRef<HTMLInputElement, RadioProps>(
  ({ className, label, description, id, ...props }, ref) => {
    const defaultId = useId()
    const radioId = id ?? defaultId

    return (
      <div className="flex items-start gap-2.5">
        <input
          ref={ref}
          type="radio"
          id={radioId}
          className={cn(
            'h-4 w-4 border-slate-300 text-primary-600 focus:ring-primary-600 focus:ring-offset-0 mt-0.5 cursor-pointer',
            'disabled:cursor-not-allowed disabled:opacity-50',
            className
          )}
          {...props}
        />
        {label && (
          <label htmlFor={radioId} className="text-sm font-medium text-slate-700 cursor-pointer select-none">
            {label}
            {description && <p className="text-xs text-slate-500 font-normal">{description}</p>}
          </label>
        )}
      </div>
    )
  }
)

Radio.displayName = 'Radio'
