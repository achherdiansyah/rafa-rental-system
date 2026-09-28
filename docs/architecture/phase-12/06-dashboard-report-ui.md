# 06 — Dashboard & Report UI (Phase 12F)

Antarmuka dashboard Admin/Owner dan halaman laporan operasional/finansial.

## Halaman

- **`/admin` (AdminDashboardPage)** — KPI bundle 1-request (`/reports/dashboard`),
  filter periode, breakdown status/trend (sejak 12B).
- **`/admin/reports/operational` (AdminOperationalReportsPage)** — tab
  `Booking | Timesheet | Utilisasi | Aktivitas Proyek | Armada`.
- **`/admin/reports/financial` (AdminFinancialReportsPage)** — tab
  `Invoice | Pembayaran | Sebagian | Outstanding | Kelebihan Bayar | Refund`.

## Komponen Reusable

- **`ReportExplorer`** — explorer laporan generik di seluruh tab:
  - event tab (active-only load; **tanpa waterfall/duplicate request** saat mount)
  - filter server: `from/to/status/customer id`
  - **search** di halaman (client, case-insensitive)
  - **sortable** kolom (tombol header → `sort_by`/`sort_dir` server)
  - **pagination** (prev/next, indikator halaman, total baris)
  - **detail**: baris bisa di-expand menampilkan kolom tambahan/ID sumber
  - **export**: Ekspor CSV memakai `downloadCsv(type, params)` (blob + token,
    download nama `rafa-report-{type}.csv`)
  - loading (skeleton), empty, error (Alert), responsive (overflow-x table),
    aria (tablist, label cari, label sort)
- `reportingService.listReport(type, params)` + `downloadCsv(type, params)`
  — satu titik akses data; `ReportQuery` menormalkan filter.

Filter/sort/pagination mengikuti backend (`per_page 20`, sort whitelist,
page). Tidak ada kalkulasi finansial baru di frontend.

## Test Result

- `AdminOperationalReportsPage.test` (5): load tab aktif 1 request; filter
  status → refetch params; sort kolom → `sort_by/sort_dir`; export CSV tipe
  aktif; ganti tab → endpoint sesuai + empty state.
- `AdminFinancialReportsPage.test` (3): invoice hanya 1 request; tab refund
  → refetch `refunds` (total 2 request bukan waterfall); export invoices.
- **Frontend total 121 passed (30 files)**; build `tsc+vite` sukses; lint bersih.

## Files

- `frontend/src/features/reporting/components/ReportExplorer.tsx`
- `frontend/src/features/reporting/pages/{AdminOperationalReportsPage,AdminFinancialReportsPage}.tsx`
- `frontend/src/features/reporting/services/reportingService.ts` (+list/export)
- `frontend/src/routes/AppRoutes.tsx`, `components/layout/AdminLayout.tsx` (nav)
- Test: `AdminOperationalReportsPage.test.tsx`, `AdminFinancialReportsPage.test.tsx`