# 06 — Refund & Outstanding Integration Test Report (Phase 11G)

Laporan verifikasi end-to-end lintasan refund, outstanding, notifikasi, deadline,
dan kualitas proses finansial.

## 1. End-to-End Flow (RefundOutstandingIntegrationTest — 5 kasus, 40 assertions)

```
Cancellation setelah payment ──> refund PENDING (CANCELLATION, sekali, nilai=Σapproved)
Overpayment (approved > total) ──> invoice OVERPAID + refund PENDING (OVERPAYMENT, sekali)
Refund PENDING ─approve(OWNER)─> APPROVED ─process(ADMIN)─> PROCESSING
             ─complete(ADMIN, bukti)─> COMPLETED  |  ─fail(ADMIN, alasan)─> FAILED (histori utuh)
Outstanding per customer (UNPAID + PARTIAL + OVERDUE), basis approved payment
Payment rejection TIDAK mengubah deadline invoice; payment ditolak tetap tersimpan
Notification: PAYMENT_APPROVED (in-app + WhatsApp delivery, sekali)
Authorization: User/Admin/Owner terpisah (view/approve/process)
Audit & financial history immutable
No duplicate refund/payment/notification; race/idempotency dalam verifikasi
```

## 2. Hasil Eksekusi

| Command | Hasil |
|---|---|
| `php artisan test` | **405 passed (2153 assertions)** |
| `./vendor/bin/pint --test` | passed |
| `npm run build` (tsc -b + vite) | sukses |
| Static analysis | tidak tersedia di repo |

## 3. Poin yang Diuji

- Cancellation-after-payment → refund tunggal (anti-duplikat; registrasi kedua
  tidak menggandakan), `paid_amount` invoice utuh.
- Overpayment → OVERPAID + `overpayment_amount` 200k; settlement refund hingga
  COMPLETED (bukti privat), satu refund.
- Fail path → FAILED + reason; baris & riwayat finansial tetap.
- Outstanding per customer: 400k (UNPAID, basis approved; REJECTED 50k
  dikecualikan) + 200k (OVERDUE) + 0 (PARTIAL) → 600k; penolakan pembayaran
  tidak mengubah `due_at` invoice; payment ditolak tersimpan.
- Notification: approval → 1 in-app + 1 WhatsApp delivery; approval kedua → 409
  `INVALID_STATE_TRANSITION` tanpa efek samping (notifikasi/refund tidak
  duplikat); `REFUND_PENDING` dinotifikasi ke Owner sekali (in-app).
- Authorization: stranger 403 lihat refund & deliveries; admin 403 approve;
  owner 403 process; owner approve 200.
- Audit: `PAYMENT_APPROVED` tercatat; mutasi finansial off-book tidak ada.

## Review

- **Race condition / idempotency** — verifikasi kedua payment yang sama ditolak
  (guard status + pessimistic lock); saldo tidak double-count.
- **Duplicate guard** — refund per sumber (invoice↔source) & notifikasi sekali.
- **Deadline invariance** — reject tidak menyentuh `due_at` (dicek timestamp).
- **Financial history immutable** — `paid_amount`/`overpayment_amount` hanya
  hasil settlement; baris tidak dihapus.
- **Audit** — tiap transisi refund/payment tercatat.

## Files

- `tests/Feature/Integration/RefundOutstandingIntegrationTest.php`
- Docs: `docs/architecture/phase-11/06-integration-test-report.md`