# Phase 12: Dashboard & Reporting — RAFA Rental System

Dokumentasi lapisan laporan/dashboard dan akses data agregat.

---

## 1. Tujuan Phase 12

Menyediakan laporan operasional & finansial read-only (booking, rental,
timesheet, utilisasi armada, keuangan, outstanding) dengan query agregat yang
efisien dan otorisasi per role.

---

## 2. Struktur Subphase

| Subphase | Fokus | Status |
|---|---|---|
| **12A** | Reporting & Query Services (agregat SQL, filter, scoping, no N+1) | Selesai (`ba767d8`) |
| **12B** | Admin Dashboard (KPI bundle 1-request, filter periode, read-only) | Selesai (`c31e66d`) |
| **12C** | Operational Reports (drillable rows, filter/paginate/sort, ARMADA admin-only) | Selesai (`cb58579`) |
| **12D** | Financial Reports (invoice/payment/partial/outstanding/overpayment/refund, approved basis) | Selesai (`7257d06`) |
| **12E** | Report Export (CSV streaming, filter-aware, scoped, chunked) | Selesai (`eba5154`) |
| **12F** | Dashboard & Report UI (reusable explorer: filter/search/sort/pagi/detail/export) | Selesai (`e8a5ed9`) |
| **12G** | Integration Testing & Review (KPI DB-match, N+1, immutability, export auth) | Selesai (Aktif) |
| 12H | Final Review & Git Merge | Pending |

---

## 3. Daftar Dokumen

- `01-reporting-query.md`: Spesifikasi `ReportingQueryService` — sumber data
  (booking/equipment/rental/timesheet/invoice/payment/refund/outstanding),
  agregasi GROUP BY, filter from/to/status/customer/project/model/rental,
  scoping User/Admin/Owner, tanpa N+1 dan tanpa mutasi data transaksi.
- `02-admin-dashboard.md`: Spesifikasi dashboard Admin/Owner — endpoint KPI
  bundle satu-request (`/reports/dashboard`), KPI operasional & finansial,
  trend per periode, tanpa kalkulasi di frontend.
- `03-operational-reports.md`: Spesifikasi laporan operasional drillable
  (booking, timesheet jam aktual, utilasi rental, status armada, aktivitas
  proyek/pelanggan) — filter/paginasi/sorting + id sumber utk penelusuran;
  read-only dan ARMADA admin-only.
- `04-financial-reports.md`: Spesifikasi laporan keuangan berbasis approved
  payment — invoice (total/paid/balance), payment, partial, outstanding,
  overpayment (+refund snapshot), refund; immutable & tanpa aturan finansial
  baru.
- `05-report-export.md`: Spesifikasi ekspor laporan ke CSV — mengikuti filter
  aktif, scoping otorisasi, streaming/chunking paginasi, nama file konsisten,
  tanpa data sensitif, tanpa mutasi sumber.
- `06-dashboard-report-ui.md`: Spesifikasi UI dashboard & laporan operasional/
  finansial — komponen reusable `ReportExplorer` (tabs, filter, search, sort,
  pagination, detail, export CSV), tanpa waterfall/duplicate request,
  loading/empty/error, responsive & accessible.
- `07-integration-test-report.md`: Laporan integrasi dashboard & reporting
  (430 backend tests): KPI vs DB, filter/sort/pagination, export scoped, N+1
  guard, dan non-mutasi transaksi.