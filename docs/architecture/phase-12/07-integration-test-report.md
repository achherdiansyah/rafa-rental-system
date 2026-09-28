# 07 — Dashboard & Reporting Integration Test Report (Phase 12G)

Laporan verifikasi end-to-end laporan: dashboard KPI, laporan operasional &
finansial, filter/sort/pagination, export, otorisasi, N+1, dan immutabilitas.

## 1. Cakupan (ReportingIntegrationTest — 5 kasus, 32 assertions)

Seed realistis: 2 customer, booking multi-status, 2 rental (COMPLETED + ONGOING)
dengan timesheet APPROVED (55.25 jam), invoice PARTIALLY_PAID / OVERDUE /
OVERPAID, payment APPROVED + REJECTED, refund OVERPAYMENT.

- **Dashboard KPI cocok DB**: bookings 5, rental aktif 1 (ONGOING), jam 55.25,
  fleet available 1 / in_use 1, invoice 3, outstanding 2 customer.
- **Operasional**: timesheet 2 baris + sort asc (12.75 dulu), activity 2 rental,
  pagination lastPage 1.
- **Finansial**: basis approved (REJECTED 50k dikecualikan), balance 700k;
  outstanding hanya OPEN (OVERDUE, overdue_flag true); overpayment excess +
  refunded snapshot 200k.
- **Export mengikuti filter**: user discoped (nama customer lain tak bocor),
  equipment export 403 user, payments export admin.
- **No N+1**: invoice/partial/outstanding → < 12 query utk dataset kecil.
- **Tidak mengubah transaksi**: overpayment_amount/approved-timesheet count/
  fleet status tetap setelah semua laporan.

## 2. Hasil Eksekusi

| Command | Hasil |
|---|---|
| `php artisan test` | **430 passed (2295 assertions)** |
| `./vendor/bin/pint --test` | passed |
| `npm run build` (tsc -b + vite) | sukses |
| Static analysis | tidak tersedia di repo |

## 3. Catatan

- Subquery agregat (`paid`, `hours`, `refunded`) via `leftJoinSub` — tetap satu
  executable plan per report.
- Status hidrasi normalisasi via trait.
- Auto-increment lintas test (rollback) → filter memakai nilai nyata, bukan id
  hardcode.

## Files

- `tests/Feature/Reports/ReportingIntegrationTest.php`
- Docs: `docs/architecture/phase-12/07-integration-test-report.md`