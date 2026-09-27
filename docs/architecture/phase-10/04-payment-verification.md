# 04 — Payment Verification (Phase 10D)

Verifikasi bukti bayar oleh Admin terhadap invoice; settlement parsial, lunas,
dan kelebihan bayar. **Refund belum otomatis (10E).**

## Alur Status

```text
SUBMITTED ──approve──> APPROVED
    │                      ├─ exact payment      → invoice PAID
    │                      ├─ partial            → invoice PARTIALLY_PAID
    │                      └─ overpayment        → invoice OVERPAID (excess dicadangkan)
    └──reject (wajib reason)──> REJECTED  (baris dipertahankan)
```

## Aturan

- **Admin memeriksa nominal terhadap invoice/balance** — aksi verifikasi hanya
  membaca nilai `amount` yang diajukan, **tidak pernah mengubah nominal**.
- **Rejection wajib reason** (min 5 karakter), tersimpan di `rejection_reason`.
- **Rejected payment tetap disimpan** — tidak ada endpoint delete.
- **User dapat upload payment baru** setelah penolakan (referensi yang sama
  diizinkan kembali; anti-duplikat hanya membungkam payment non-REJECTED).
- **Deadline tetap mengikuti invoice awal** — `due_at` tidak pernah dihitung
  ulang oleh verifikasi.
- **Satu payment hanya untuk satu invoice** (FK `invoice_id`).

## Settlement

```
totalBooked = paid_amount + payment.amount
excess      = max(0, totalBooked − grand_total)     → overpayment_amount
newPaid     = min(grand_total, totalBooked)          → paid_amount
status invoice:
    excess > 0              → OVERPAID
    newPaid >= grand_total  → PAID
    else                    → PARTIALLY_PAID
```

- Exact → `PAID` (balance 0). Partial → `PARTIALLY_PAID` (balance > 0); payment
  selanjutnya dapat menyelesaikan sisa.
- Overpayment → payment tetap diverifikasi (`APPROVED`), kelebihan dicatat
  `overpayment_amount` dan invoice berstatus `OVERPAID`; **refund tidak dibuat
  otomatis** (manual, 10E).

## Arsitektur

| Komponen | Peran |
|---|---|
| `VerifyPaymentAction::approve()` | Guard SUBMITTED & invoice belum lunas; settlement; audit `PAYMENT_APPROVED` |
| `VerifyPaymentAction::reject()` | Guard SUBMITTED + reason wajib; `REJECTED` + `rejection_reason`; audit `PAYMENT_REJECTED` |
| `RejectPaymentRequest` | `reason` required min 5; admin-only |
| `PaymentController::approve/reject` | `POST /api/v1/payments/{payment}/approve|reject` |
| `PaymentPolicy::manage` | admin-only (approve/reject); tanpa delete |

## Endpoints

```
POST /api/v1/payments/{payment}/approve    admin  settlement (PAID / PARTIAL / OVERPAID)
POST /api/v1/payments/{payment}/reject     admin  reason wajib; baris dipertahankan
GET  /api/v1/payments/{payment}            owner/admin  detail + bukti
```

## Test Result — `PaymentVerificationTest` (7 kasus)

- **Approve exact** → payment APPROVED, invoice PAID, `paid_amount`=total,
  balance 0, `verified_by` admin, **`due_at` tidak berubah**, amount tetap.
- **Partial lalu lunas** → `PARTIALLY_PAID` (balance 500k) → payment kedua →
  `PAID` (multi-payment 1 invoice).
- **Overpayment** → payment APPROVED, invoice OVERPAID, `overpayment_amount`
  200k; **refund tidak dibuat** (tabel refund kosong) .
- **Reject** → REJECTED + reason tersimpan; invoice & due_at tak tersentuh; baris
  tetap ada.
- **Re-upload** → referensi sama setelah penolakan diizinkan → approve → PAID.
- **Authorization** → user 403 approve/reject; reason < 5 → 422; proses ganda
  → 409.
- **Audit** → event `PAYMENT_APPROVED` & `PAYMENT_REJECTED` tercatat.

Note: endpoint verifikasi memakai rute param tunggal `{payment}` agar model
binding konsisten dengan pola controller yang sudah ada (`/api/v1/payments/{payment}`).