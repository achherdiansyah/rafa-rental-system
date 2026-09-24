import React, { Suspense, lazy } from 'react'
import { Routes, Route } from 'react-router-dom'
import { LoadingState } from '@/components/feedback/LoadingState'
import { PublicLayout } from '@/components/layout/PublicLayout'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { UserLayout } from '@/components/layout/UserLayout'
import { AdminLayout } from '@/components/layout/AdminLayout'
import { OwnerLayout } from '@/components/layout/OwnerLayout'
import { ProtectedRoute } from './ProtectedRoute'
import { RoleRoute } from './RoleRoute'
import { GuestRoute } from './GuestRoute'

// Lazy-loaded route views
const HomePage = lazy(() => import('@/pages/HomePage'))
const LoginPage = lazy(() => import('@/pages/LoginPage'))
const RegisterPage = lazy(() => import('@/pages/RegisterPage'))
const ForgotPasswordPage = lazy(() => import('@/pages/ForgotPasswordPage'))
const ResetPasswordPage = lazy(() => import('@/pages/ResetPasswordPage'))
const ProfilePage = lazy(() => import('@/pages/ProfilePage'))
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage'))
const ForbiddenPage = lazy(() => import('@/pages/ForbiddenPage'))
const UnauthorizedPage = lazy(() => import('@/pages/UnauthorizedPage'))
const UserPortalPlaceholder = lazy(() => import('@/pages/UserPortalPlaceholder'))
const AdminPortalPlaceholder = lazy(() => import('@/pages/AdminPortalPlaceholder'))
const OwnerPortalPlaceholder = lazy(() => import('@/pages/OwnerPortalPlaceholder'))
const AdminEquipmentMasterPage = lazy(() => import('@/features/equipment/pages/AdminEquipmentMasterPage'))
const AdminEquipmentUnitsPage = lazy(() => import('@/features/equipment/pages/AdminEquipmentUnitsPage'))

export const AppRoutes: React.FC = () => {
  return (
    <Suspense fallback={<LoadingState message="Memuat halaman..." className="min-h-screen" />}>
      <Routes>
        {/* 1. Public Routes */}
        <Route element={<PublicLayout />}>
          <Route path="/" element={<HomePage />} />
          <Route path="/unauthorized" element={<UnauthorizedPage />} />
          <Route path="/forbidden" element={<ForbiddenPage />} />
        </Route>

        {/* 2. Guest-Only Authentication Routes */}
        <Route element={<GuestRoute />}>
          <Route element={<AuthLayout />}>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password" element={<ResetPasswordPage />} />
          </Route>
        </Route>

        {/* 3. Protected Routes */}
        <Route element={<ProtectedRoute />}>
          {/* 3.1 Customer Portal (/app/*) - Restricted to USER */}
          <Route element={<RoleRoute allowedRoles={['USER']} />}>
            <Route path="/app" element={<UserLayout />}>
              <Route index element={<UserPortalPlaceholder />} />
              <Route path="equipment" element={<UserPortalPlaceholder />} />
              <Route path="bookings" element={<UserPortalPlaceholder />} />
              <Route path="invoices" element={<UserPortalPlaceholder />} />
              <Route path="profile" element={<ProfilePage />} />
            </Route>
          </Route>

          {/* 3.2 Admin Portal (/admin/*) - Restricted to ADMIN and OWNER */}
          <Route element={<RoleRoute allowedRoles={['ADMIN', 'OWNER']} />}>
            <Route path="/admin" element={<AdminLayout />}>
              <Route index element={<AdminPortalPlaceholder />} />
              <Route path="equipment" element={<AdminEquipmentMasterPage />} />
              <Route path="bookings" element={<AdminPortalPlaceholder />} />
              <Route path="units" element={<AdminEquipmentUnitsPage />} />
              <Route path="timesheets" element={<AdminPortalPlaceholder />} />
              <Route path="payments" element={<AdminPortalPlaceholder />} />
              <Route path="refunds" element={<AdminPortalPlaceholder />} />
            </Route>
          </Route>

          {/* 3.3 Owner Portal (/owner/*) - Restricted exclusively to OWNER */}
          <Route element={<RoleRoute allowedRoles={['OWNER']} />}>
            <Route path="/owner" element={<OwnerLayout />}>
              <Route index element={<OwnerPortalPlaceholder />} />
              <Route path="revenue" element={<OwnerPortalPlaceholder />} />
              <Route path="pricing" element={<OwnerPortalPlaceholder />} />
              <Route path="audit" element={<OwnerPortalPlaceholder />} />
              <Route path="settings" element={<OwnerPortalPlaceholder />} />
            </Route>
          </Route>
        </Route>

        {/* 4. Fallback 404 Route */}
        <Route path="*" element={<PublicLayout />}>
          <Route path="*" element={<NotFoundPage />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
