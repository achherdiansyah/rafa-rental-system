# 01 — Reporting & Query Services (Phase 12A)

Lapisan laporan read-only, terpisah dari business logic, berbasis agregasi SQL.

## Prinsip

- **Report service terpisah dari business logic** — `App\Services\Reports\ReportingQueryService`
  hanya query; tidak pernah menulis/menghapus (tidak ada mutasi transaksi).
- **Sumber data**: booking, rental, timesheet, invoice, payment, refund,
  outstanding (reuse `CustomerOutstandingService`), equipment (utilisasi).
- **Query efisien**: semua agregasi lewat `GROUP BY` + `SUM`/`COUNT` di DB;
  tanpa eager-load per-baris; guard N+1 diuji (`< 5` query utk summary).
- **Filter**: `from`, `to`, `status`, `customer_id`, `project_id`, `model_id`,
  `rental_id` — diterapkan pada kolom periode (created_at / report_date).
- **Authorization**: USER mendapat scope sendiri (`user_id`), ADMIN/OWNER
  penuh; utilisasi armada hanya ADMIN/OWNER (403 utk USER).

## Query Yang Tersedia

| Method | Isi |
|---|---|
| `bookingSummary(filters, scopedUser)` | total, customer_count, count per status |
| `rentalSummary(filters, scopedUser)` | total, count per status, count per project |
| `timesheetSummary(filters, scopedUser)` | total_hours per bulan + per project (APPROVED saja) |
| `equipmentUtilization(filters)` | total_hours + per model (brand/model), unit_count, rental_lines |
| `financialSummary(filters, scopedUser)` | invoice per status (grand/paid/balance), payments APPROVED (count+amount), refunds per status, outstanding (customer_count, total, per-customer) |

Semua menerima `period {from,to}`. Scope `scopedUser` membatasi booking/rental/
timesheet/invoice/payment/refund/outstanding milik customer itu.

## Endpoints (read-only)

```
GET /api/v1/reports/bookings?from&to&status&customer_id
GET /api/v1/reports/rentals?from&to&status&project_id
GET /api/v1/reports/timesheet?from&to&project_id&rental_id
GET /api/v1/reports/financial?from&to
GET /api/v1/reports/equipment-utilization?from&to&model_id   (ADMIN/OWNER)
```

## Notes

- Status dari `GROUP BY` Eloquent ter-hydrate sebagai enum → dinormalisasi ke
  string lewat `statusValue()`.
- Utilisasi menghitung jam kerja dari timesheet **APPROVED** via
  rental_detail→assignment→unit→model.
- Outstanding report memakai service agregasi yang sama (bukan duplikat query).

## Test Result — `ReportingQueryServiceTest` (6 kasus, 30 assertions)

- Booking summary by status + period + scoped user; status filter.
- Rental summary by status & project + filter project.
- Timesheet/utilization hanya APPROVED (DRAFT 40 jam dikecualikan).
- Financial summary + scoping antar-customer (tidak bocor); data tidak berubah
  (grand_total/paid_amount utuh).
- No N+1 (query count < 5 utk 5 booking).
- API auth: user scoped total; utilization 403 utk USER; admin melihat semua.

## Files

- `app/Services/Reports/ReportingQueryService.php`
- `app/Http/Controllers/Api/V1/ReportingController.php`
- `routes/api.php` (group `reports`)
- `tests/Feature/Reports/ReportingQueryServiceTest.php`