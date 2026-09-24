import React from 'react'
import { ChevronRight } from 'lucide-react'
import { cn } from '@/utils/cn'

export interface BreadcrumbItem {
  label: string
  href?: string
}

export interface BreadcrumbProps {
  items: BreadcrumbItem[]
  className?: string
}

export const Breadcrumb: React.FC<BreadcrumbProps> = ({ items, className }) => {
  return (
    <nav aria-label="Breadcrumb" className={cn('flex', className)}>
      <ol className="flex items-center space-x-2 text-sm text-slate-500">
        {items.map((item, index) => {
          const isLast = index === items.length - 1

          return (
            <li key={index} className="flex items-center">
              {item.href && !isLast ? (
                <a
                  href={item.href}
                  className="hover:text-primary-600 transition-colors"
                >
                  {item.label}
                </a>
              ) : (
                <span
                  className={cn(isLast ? 'font-semibold text-slate-800' : 'text-slate-500')}
                  aria-current={isLast ? 'page' : undefined}
                >
                  {item.label}
                </span>
              )}
              
              {!isLast && (
                <ChevronRight size={14} className="mx-1 shrink-0 text-slate-400" />
              )}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
