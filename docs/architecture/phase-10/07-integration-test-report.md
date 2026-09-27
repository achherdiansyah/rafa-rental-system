# 07 — Billing & Payment Integration Test Report (Phase 10G)

Laporan verifikasi end-to-end lintasan keuangan: billing → invoice → deadline →
submission → verifikasi → settlement, plus review kualitas.

## 1. End-to-End Flow (BillingPaymentIntegrationTest — 3 kasus, 85 assertions)

```
Booking approved (CONFIRMED)
 → rental COMPLETED (timesheet APPROVED)
 → Billing calculation (RentalBillingService, snapshot, MOB/DEMOB)
 → Invoice issued (DAILY_WORK)  →  due_at = issued_at + 24 jam
 → Payment submitted (proof, private)
 → Admin verification
    ├─ exact / partial → PAID / PARTIALLY_PAID
    ├─ overpayment     → OVERPAID + overpayment_amount (refund belum otomatis)
    └─ rejected        → REJECTED (reason), deadline tidak direset
```

Diuji juga:

- **Payment rejected** — baris dipertahankan; invoice & `due_at` tidak tersentuh.
- **Re-upload** — referensi yang sama setelah penolakan diizinkan → approve → PAID.
- **Deadline tetap** — verifikasi tidak pernah memutasi `due_at`; reject tidak reset.
- **Multiple payment** — 3× partial → `paid_amount` == Σ approved == total;
  status PARTIALLY_PAID → PAID; balance 0.
- **One payment one invoice** — setiap payment terikat 1 invoice (FK + periksa).
- **Historical price** — kenaikan master tarif pasca-invoice tidak mengubah
  `grand_total` maupun `paid_amount` (riwayat kebal).
- **Invalid state** — approve berulang payment APPROVED → 409
  `INVALID_STATE_TRANSITION`.
- **Authorization** — user lain tidak boleh submit / lihat / verifikasi (403).
- **Duplicate payment handling** — referensi sama yang non-REJECTED → 409
  `BUSINESS_RULE_VIOLATION`.

## 2. Hasil Eksekusi

| Command | Hasil |
|---|---|
| `php artisan test` | **374 passed (1991 assertions)** |
| `./vendor/bin/pint --test` | passed |
| `npm run test` (vitest) | **102 passed (25 files)** |
| `npm run build` (tsc -b + vite) | sukses |
| `npm run lint` | tanpa error |
| Static analysis (larastan/phpstan) | tidak tersedia di repo |

## 3. Review

### Duplicate balance — AMAN
Saldo billing berbasis `Σ payments APPROVED` (sumber tunggal), bukan akumulasi
kolom; pembayaran ditolak tidak masuk hitungan.

### Race condition payment verification — DIPERKOKOH
`VerifyPaymentAction::approve/reject` kini membaca ulang payment dengan
`lockForUpdate()` (pessimistic lock) di dalam transaksi sebelum memutasi —
mencegah dua verifikasi konkuren menyelesaikan settlement ganda.

### N+1 — AMAN
Unit kerja keuangan memakai eager loading (`attachments`, `invoice.booking.projectLocation`, `details`); `queue` dan list invoice dipaginate.

### Financial history mutation — AMAN
Tidak ada nilai historis yang diubah hasil verifikasi: `payment.amount`,
`issued_at`, `grand_total` tetap; yang diperbarui hanya status settlement +
`paid_amount`/`overpayment_amount` + `verified_by`; satu-satunya mutasi `due_at`
lewat `ExtendInvoiceDeadlineAction` (wajib audit).

### Security file upload — AMAN
Bukti wajib via `FileSecurity` (mime/ekstensi/5MB), tersimpan disk private
`local`, penyajian via `GET /payments/{id}/proof` yang di-policy (owner/admin);
gambar/PDF diverifikasi.

### Unauthorized financial access — AMAN
`InvoicePolicy`/`PaymentPolicy` membatasi: non-owner user 403 pada submit,
viewing, dan verifikasi (queues & proof admin-only).

## 4. Files

- `tests/Feature/Billing/BillingPaymentIntegrationTest.php`
- `app/Actions/Payment/VerifyPaymentAction.php` (pessimistic lock)
- Docs: `docs/architecture/phase-10/07-integration-test-report.md`