import React from 'react'
import { Outlet } from 'react-router-dom'
import { Navbar } from './Navbar'

export const PublicLayout: React.FC = () => {
  return (
    <div className="flex flex-col min-h-screen bg-slate-50">
      <Navbar />
      <main className="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Outlet />
      </main>
      <footer className="border-t border-slate-200 py-8 bg-white text-center text-sm text-slate-500">
        &copy; {new Date().getFullYear()} PT RAFA Rental Nusantara. All rights reserved.
      </footer>
    </div>
  )
}
