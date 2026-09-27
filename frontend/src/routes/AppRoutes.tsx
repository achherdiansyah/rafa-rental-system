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
const OwnerPricingPage = lazy(() => import('@/features/equipment/pages/OwnerPricingPage'))
const AdminEquipmentMasterPage = lazy(() => import('@/features/equipment/pages/AdminEquipmentMasterPage'))
const AdminEquipmentUnitsPage = lazy(() => import('@/features/equipment/pages/AdminEquipmentUnitsPage'))
const AdminBankAccountsPage = lazy(() => import('@/features/bank/pages/AdminBankAccountsPage'))
const EquipmentCatalogPage = lazy(() => import('@/features/equipment/pages/EquipmentCatalogPage'))
const EquipmentDetailPage = lazy(() => import('@/features/equipment/pages/EquipmentDetailPage'))
const RecommendationPage = lazy(() => import('@/features/recommendation/pages/RecommendationPage'))
const UserProjectLocationsPage = lazy(() => import('@/features/project/pages/UserProjectLocationsPage'))
const UserCartPage = lazy(() => import('@/features/cart/pages/UserCartPage'))
const UserBookingsPage = lazy(() => import('@/features/booking/pages/UserBookingsPage'))
const AdminBookingsPage = lazy(() => import('@/features/booking/pages/AdminBookingsPage'))
const AdminRentalsPage = lazy(() => import('@/features/rental/pages/AdminRentalsPage'))
const UserRentalsPage = lazy(() => import('@/features/rental/pages/UserRentalsPage'))
const UserTimesheetsPage = lazy(() => import('@/features/timesheet/pages/UserTimesheetsPage'))
const AdminTimesheetsPage = lazy(() => import('@/features/timesheet/pages/AdminTimesheetsPage'))
const UserInvoicesPage = lazy(() => import('@/features/invoice/pages/UserInvoicesPage'))
const AdminInvoicesPage = lazy(() => import('@/features/invoice/pages/AdminInvoicesPage'))
const AdminPaymentsPage = lazy(() => import('@/features/invoice/pages/AdminPaymentsPage'))
const UserRefundsPage = lazy(() => import('@/features/refund/pages/UserRefundsPage'))
const UserOutstandingPage = lazy(() => import('@/features/refund/pages/UserOutstandingPage'))
const AdminRefundsPage = lazy(() => import('@/features/refund/pages/AdminRefundsPage'))
const AdminOutstandingPage = lazy(() => import('@/features/refund/pages/AdminOutstandingPage'))
const UserNotificationsPage = lazy(() => import('@/features/notification/pages/UserNotificationsPage'))
const AdminNotificationsPage = lazy(() => import('@/features/notification/pages/AdminNotificationsPage'))

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
              <Route path="equipment" element={<EquipmentCatalogPage />} />
              <Route path="equipment/:id" element={<EquipmentDetailPage />} />
              <Route path="recommendations" element={<RecommendationPage />} />
              <Route path="recommendation" element={<RecommendationPage />} />
              <Route path="locations" element={<UserProjectLocationsPage />} />
              <Route path="cart" element={<UserCartPage />} />
              <Route path="bookings" element={<UserBookingsPage />} />
              <Route path="rentals" element={<UserRentalsPage />} />
              <Route path="timesheets" element={<UserTimesheetsPage />} />
              <Route path="invoices" element={<UserInvoicesPage />} />
              <Route path="refunds" element={<UserRefundsPage />} />
              <Route path="outstanding" element={<UserOutstandingPage />} />
              <Route path="notifications" element={<UserNotificationsPage />} />
              <Route path="profile" element={<ProfilePage />} />
            </Route>
          </Route>

          {/* 3.2 Admin Portal (/admin/*) - Restricted to ADMIN and OWNER */}
          <Route element={<RoleRoute allowedRoles={['ADMIN', 'OWNER']} />}>
            <Route path="/admin" element={<AdminLayout />}>
              <Route index element={<AdminPortalPlaceholder />} />
              <Route path="equipment" element={<AdminEquipmentMasterPage />} />
              <Route path="units" element={<AdminEquipmentUnitsPage />} />
              <Route path="banks" element={<AdminBankAccountsPage />} />
              <Route path="bookings" element={<AdminBookingsPage />} />
              <Route path="rentals" element={<AdminRentalsPage />} />
              <Route path="timesheets" element={<AdminTimesheetsPage />} />
              <Route path="invoices" element={<AdminInvoicesPage />} />
              <Route path="payments" element={<AdminPaymentsPage />} />
              <Route path="refunds" element={<AdminRefundsPage />} />
              <Route path="outstanding" element={<AdminOutstandingPage />} />
              <Route path="notifications" element={<AdminNotificationsPage />} />
            </Route>
          </Route>

          {/* 3.3 Owner Portal (/owner/*) - Restricted exclusively to OWNER */}
          <Route element={<RoleRoute allowedRoles={['OWNER']} />}>
            <Route path="/owner" element={<OwnerLayout />}>
              <Route index element={<OwnerPortalPlaceholder />} />
              <Route path="revenue" element={<OwnerPortalPlaceholder />} />
              <Route path="pricing" element={<OwnerPricingPage />} />
              <Route path="audit" element={<OwnerPortalPlaceholder />} />
              <Route path="settings" element={<AdminBankAccountsPage />} />
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
