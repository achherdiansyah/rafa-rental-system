# 04 — Financial Reports (Phase 12D)

Laporan finansial baris (read-only) berbasis **approved payment**.

## Laporan

| Endpoint | Isi |
|---|---|
| `/reports/financial/invoices` | invoice (number, type, status, issued/due, grand_total, **paid_total (basis approved)**, balance) + customer/project |
| `/reports/financial/payments` | payment (status, amount, payment_date, sender, reference, rejection_reason) + invoice/customer/project |
| `/reports/financial/partials` | invoice `PARTIALLY_PAID`: approved_count, last_payment_at, paid_total, balance |
| `/reports/financial/outstanding` | invoice terbuka (`ISSUED/UNPAID/PARTIALLY_PAID/OVERDUE`): balance approved + `overdue_flag` |
| `/reports/financial/overpayments` | invoice `OVERPAID`: overpayment_amount + refunded_total (refund snapshot, non-FAILED) |
| `/reports/financial/refunds` | refund (source, amount, status, reason, transfer_reference, timestamps) + customer |

## Aturan

- **Approved payment sebagai satu-satunya dasar paid** — `leftJoinSub` atas
  payments `APPROVED`; REJECTED tidak pernah dihitung.
- **Membedakan** `grand_total` (total invoice), `paid_total` (bayar terverifikasi),
  `balance` (sisa), `refunded_total`/`overpayment_amount` (refund/excess).
- **Filter** periode (created_at / payment_date / due_at), customer, project,
  status, source (refund).
- **Historical financial data immutable** — laporan tidak menulis apa pun;
  status diambil apa adanya, tanpa aturan finansial baru.
- USER discope ke data dirinya; admin/owner penuh (kecuali endpoint finansial
  tetap scoped utk user sesuai kepemilikan).

## Proses

`FinancialReportService` memakai trait `FiltersAndPagination` (dipindah dari
`OperationalReportService`) — filter/paginasi/sorting utk reuse DRY. Sub-query
agregat (`paid`, `meta payments`, `refunded`) via `leftJoinSub` → tanpa N+1.

## Test Result — `FinancialReportTest` (5 kasus, 27 assertions)

- Invoice: basis approved (REJECTED 50k di luar paid), balance benar.
- Partial: approved_count, last_payment_at, paid.
- Outstanding: hanya status terbuka (PAID diremukkan), `overdue_flag`.
- Overpayment: excess + refunded_total (snapshot) + paid basis.
- Refund: baris + filter source + customer.
- Scoped user isolation (invoice customer lain tak bocor) + immutability
  (grand_total/status tetap).
- API finansial dapat diakses (scoped).

**Backend total 421 passed (2245 assertions)**; Pint clean. Frontend tak tersentuh.

## Files

- `app/Services/Reports/FinancialReportService.php`
- `app/Services/Reports/Concerns/FiltersAndPagination.php` (+ di-refactor dari Operational)
- `app/Http/Controllers/Api/V1/ReportingController.php` (financial*)
- `routes/api.php` (group `reports/financial`)
- `tests/Feature/Reports/FinancialReportTest.php`