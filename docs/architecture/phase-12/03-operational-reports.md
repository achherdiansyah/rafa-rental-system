# 03 — Operational Reports (Phase 12C)

Laporan operasional berbaris (drillable) dengan filter, paginasi, dan sorting.

## Laporan

| Endpoint | Isi | Sumber |
|---|---|---|
| `/reports/operational/bookings` | booking (code, customer, project, status, total, tanggal) | `bookings` |
| `/reports/operational/timesheets` | timesheet jam aktual (date, unit, model, project, HM, break, work/standby/breakdown, operator, status) | `timesheets` (basis approved) |
| `/reports/operational/rentals` | utilasi rental per detail (rental, unit, model, project, status, started/completed, total jam approved) | `rental_details` + subquery hours |
| `/reports/operational/equipment` | status armada + akumulasi jam (ADMIN/OWNER) | `equipment_units` + subquery hours |
| `/reports/operational/activity` | aktivitas proyek/pelanggan (rental, customer, project, status, jam, `paid_total` dari invoice) | `rentals` + subqueries |

## Fitur

- **Filter**: `from`, `to`, `status`, `customer_id`, `project_id`, `model_id`,
  `unit_id`, `rental_id` (per report sesuai kolom terkait).
- **Pagination**: `page`, `per_page` (1–100).
- **Sorting**: `sort_by` whitelist per report + `sort_dir` (asc/desc); default
  id desc.
- **Drill-down**: tiap baris membawa id sumber (`booking_id`, `rental_id`,
  `rental_detail_id`, `unit_id`, `model_id`, `project_location_id`,
  `customer_id`) agar UI bisa menautkan ke transaksi.
- **Read-only**: murni query; tidak ada mutasi.
- **Akses**: USER discope ke data miliknya; laporan armada (`equipment`)
  dan dashboard → ADMIN/OWNER (403 utk USER).

## Proses Query

`OperationalReportService` memakai `DB::table` + join + alias subquery
(`leftJoinSub`) utk agregasi jam/dibayar tanpa N+1; select eksplisit agar tidak
tabrakan kolom; `leftJoinSub` utk `SUM` approved timesheet per rental detail /
unit / rental.

## Test Result — `OperationalReportTest` (4 kasus, 23 assertions)

- Booking: rows + filter status/periode + pagination + drill ids + scoped user.
- Timesheet: filter model + sort `total_work_hours` asc (+unit_serial).
- Utilisasi & aktivitas: jam approved + `paid_total` invoice + filter project +
  scoped user (tidak menampilkan rental customer lain).
- Equipment: 403 utk USER; admin melihat status + serial; data tidak berubah.

**Backend total 416 passed (2218 assertions)**; Pint clean. Frontend tak tersentuh.

## Files

- `app/Services/Reports/OperationalReportService.php`
- `app/Http/Controllers/Api/V1/ReportingController.php` (metode operational* + helper `paginated`)
- `routes/api.php` (group `reports/operational`)
- `tests/Feature/Reports/OperationalReportTest.php`