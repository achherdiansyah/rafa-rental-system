# 01 — Full Regression & Test Coverage (Phase 13A)

Regression menyeluruh Phase 1–12 + penambahan coverage business rule.

## Strategy

1. **Jalankan seluruh suite** — backend unit/feature/integration + frontend.
2. **Regression**: tak ada test fase sebelumnya yang rusak.
3. **Coverage tambahan** untuk aturan yang belum tertutup:
   - matriks kemampuan peran (Phase 1 permission matrix)
   - boundary policy finansial (Invoice/Payment/Refund/Timesheet)
   - golden path end-to-end (booking → rental → timesheet → invoice → payment)

## Test Baru

### `PermissionMatrixTest` (Security — 2 kasus, 85 assertions+)
- **Matriks gate per role** (`Gate::forUser`): `[USER, ADMIN, OWNER]`
  - ADMIN+OWNER: view-admin-dashboard, manage-equipment, approve-booking,
    verify-payment, manage-bank-accounts
  - ADMIN saja: assign-units, dispatch-unit, validate-bast, validate-timesheet,
    process-refund
  - OWNER saja: view-owner-dashboard, view-revenue-reports, view-audit-logs,
    manage-pricing-master, approve-refund, decommission-equipment, deactivate-user
- **Policy boundary**: InvoicePolicy.manage → ADMIN; RefundPolicy.approve →
  OWNER, manage → ADMIN; PaymentPolicy.manage → ADMIN;
  TimesheetPolicy.validate → ADMIN.

### `CoreFlowRegressionTest` (Integration — golden path)
Satu alur API penuh dengan assertions konsistensi di tiap hop:

```
CONFIRMED → rental dispatch/arrive/start → timesheet (8h) → approve →
return/inspect → READY (unit AVAILABLE, rental COMPLETED) →
invoice DAILY_WORK (8h × 100k = 800k) → issue (due_at terisi) →
payment approved → invoice PAID (paid == grand, balance 0)
```

Cross-check: booking sumber tetap CONFIRMED, timesheet APPROVED, unit bebas
untuk sewa berikutnya, tidak ada refund tersisa, invoice punya 1 detail.

## Hasil Eksekusi

| Command | Hasil |
|---|---|
| `php artisan test` | **433 passed (2382 assertions)** |
| `./vendor/bin/pint --test` | passed |
| `npm run test` | **121 passed (30 files)** |
| `npm run build` (tsc -b + vite) | sukses |
| Static analysis | tidak tersedia di repo |

Tidak ada regression baru; semua suite fase sebelumnya hijau.

## Files

- `tests/Feature/Security/PermissionMatrixTest.php`
- `tests/Feature/Integration/CoreFlowRegressionTest.php`
- Docs: `docs/architecture/phase-13/01-regression-coverage.md`