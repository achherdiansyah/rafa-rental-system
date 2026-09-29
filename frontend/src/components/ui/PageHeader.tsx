import React from 'react'

export interface PageHeaderProps {
  title: string
  subtitle?: string
  actions?: React.ReactNode
}

/**
 * Standard page header: [Title]            [ Primary CTA ]
 * subtitle below title; CTA aligned right. Spacious, corporate.
 */
export const PageHeader: React.FC<PageHeaderProps> = ({ title, subtitle, actions }) => {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0">
        <h1 className="page-title">{title}</h1>
        {subtitle && <p className="page-subtitle">{subtitle}</p>}
      </div>
      {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
    </div>
  )
}
export default PageHeader