import React from 'react'
import { Loader2 } from 'lucide-react'
import { cn } from '@/utils/cn'

export interface LoadingStateProps {
  message?: string
  className?: string
}

export const LoadingState: React.FC<LoadingStateProps> = ({
  message = 'Loading data...',
  className,
}) => {
  return (
    <div
      role="status"
      className={cn(
        'flex flex-col items-center justify-center p-8 sm:p-12 text-center text-slate-500',
        className
      )}
    >
      <Loader2 className="animate-spin text-primary-600 mb-3" size={32} />
      <span className="text-sm font-medium text-slate-600">{message}</span>
      <span className="sr-only">Loading</span>
    </div>
  )
}
