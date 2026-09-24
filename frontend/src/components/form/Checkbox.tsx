import React, { useId } from 'react'
import { cn } from '@/utils/cn'

export interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string
  description?: string
  error?: string
}

export const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
  ({ className, label, description, error, id, ...props }, ref) => {
    const defaultId = useId()
    const checkboxId = id ?? defaultId

    return (
      <div className="flex flex-col gap-1">
        <div className="flex items-start gap-2.5">
          <input
            ref={ref}
            type="checkbox"
            id={checkboxId}
            className={cn(
              'h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-600 focus:ring-offset-0 mt-0.5 cursor-pointer',
              'disabled:cursor-not-allowed disabled:opacity-50',
              error && 'border-rose-500',
              className
            )}
            {...props}
          />
          {label && (
            <label htmlFor={checkboxId} className="text-sm font-medium text-slate-700 cursor-pointer select-none">
              {label}
              {description && <p className="text-xs text-slate-500 font-normal">{description}</p>}
            </label>
          )}
        </div>
        {error && <p className="text-sm text-rose-500 font-medium">{error}</p>}
      </div>
    )
  }
)

Checkbox.displayName = 'Checkbox'
