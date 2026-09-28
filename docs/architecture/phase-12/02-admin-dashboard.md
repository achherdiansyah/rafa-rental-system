# 02 — Admin Dashboard (Phase 12B)

Dashboard ringkasan operasional Admin/Owner untuk data agregat.

## Endpoint (single request)

`GET /api/v1/reports/dashboard?from&to` (ADMIN/OWNER; user → 403) — satu
panggilan KPI bundle lewat `ReportingQueryService::dashboard()`, menggabungkan:

- `bookings`: total + by_status
- `rentals`: total, active (ONGOING), by_status, by_project
- `timesheet`: total_hours + by_month
- `equipment`: fleet_total, available, in_use, maintenance, utilization_hours,
  top_models (5 teratas)
- `financial`: invoices per status (grand/paid/balance), payments APPROVED
  (count+amount), refunds per status
- `outstanding`: customer_count + total

`equipmentStatusCounts()` — snapshot fleet baru (AVAILABLE / ON_SITE+MOB+DEMOB+
RETURN_INSPECTION / MAINTENANCE). Semua tetap read-only; **tidak ada kalkulasi
bisnis di frontend**.

## UI (`/admin` — AdminDashboardPage)

- Filter periode `Dari/Sampai` + tombol Terapkan & Reset → refetch `{from,to}`.
- KPI cards: Booking, Rental Aktif, Armada (available/fleet), Jam Kerja,
  Pembayaran Disetujui, Outstanding, Invoice, Refund.
- Breakdown: Status Booking, Rental per Proyek, Trend Jam Kerja per Bulan
  (bar sederhana), Invoice per Status.
- Loading (skeleton), empty (per seksi), error (Alert); 1 request per muat.

## Test Result

**Backend — 412 passed (2195 assertions)** (termasuk `dashboard()` bundle +
equipment counts + endpoint admin-only 403 utk user).

**Frontend — 113 passed (28 files)**:
- `AdminDashboardPage.test` (3): KPI dari satu panggilan; filter periode
  refetch `{to}`; reset → `{}`.
- Routing/auth integration diperbarui ke judul baru (`Dashboard Operasional`);
  halaman tidak lagi bergantung `ToastProvider` di konteks route-test.
- Build `tsc+vite` sukses; lint bersih.

## Files

- `app/Services/Reports/ReportingQueryService.php` (+`dashboard`, +`equipmentStatusCounts`)
- `app/Http/Controllers/Api/V1/ReportingController.php` (+`dashboard`)
- `routes/api.php` (`GET /reports/dashboard`)
- `frontend/src/features/reporting/{pages/AdminDashboardPage,services/reportingService}`
- `frontend/src/types/reporting.ts`
- `tests/Feature/Reports/ReportingQueryServiceTest.php`, `AdminDashboardPage.test.tsx`