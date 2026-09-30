import React, { useState } from 'react'
import { Outlet } from 'react-router-dom'
import { Navbar } from './Navbar'
import { RoleSidebar } from './sidebar/RoleSidebar'
import { ownerMenu } from './sidebarNavigation'

export const OwnerLayout: React.FC = () => {
  const [isMobileOpen, setIsMobileOpen] = useState(false)

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col" data-role="owner">
      <Navbar showMenuToggle hideMenu onMenuToggle={() => setIsMobileOpen((prev) => !prev)} />
      <div className="flex flex-1">
        <RoleSidebar
          groups={ownerMenu}
          title="Owner Governance"
          isOpen={isMobileOpen}
          onClose={() => setIsMobileOpen(false)}
        />
        <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-6xl w-full">
          <Outlet />
        </main>
      </div>
    </div>
  )
}