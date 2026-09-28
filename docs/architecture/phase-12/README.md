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
| **12C** | Operational Reports (drillable rows, filter/paginate/sort, ARMADA admin-only) | Selesai (Aktif) |
| 12D | Exports (CSV/PDF) | Pending |
| 12E | Integration Testing & Final Review | Pending |

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