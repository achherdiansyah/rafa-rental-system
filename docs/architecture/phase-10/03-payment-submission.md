# 03 — Payment Submission (Phase 10C)

Pengajuan pembayaran oleh user terhadap invoice: memilih invoice, mengisi
nominal/tanggal/pengirim/referensi, dan mengunggah bukti transfer.

## Alur Status

```text
PAYMENT_PENDING (PENDING) → SUBMITTED   (user upload proof)
SUBMITTED → APPROVED | REJECTED         (Admin verifikasi — Phase 10D, belum di sini)
```

- Payment **belum dianggap approved** sampai Admin memverifikasi (tidak ada
  auto-approval, tidak ada perubahan status invoice secara otomatis).
- Payment **ditolak tidak pernah dihapus** — verifikasi/penolakan ditangani 10D;
  aksi ini tidak pernah memutasi payment lama.

## Aturan

- **1 payment hanya untuk 1 invoice** (`invoice_id` FK); **1 invoice boleh punya
  banyak payment** (pembayaran bertahap/DP).
- Bukti transfer **wajib** dan divalidasi sesuai spesifikasi keamanan
  (`FileSecurity`: `pdf/jpg/jpeg/png/webp`, MIME cocok, maks 5 MB).
- Bukti tersimpan di disk **private `local`** (URL tidak terekspos).
- Nominal harus > 0; tanggal transfer wajib (`payment_date`); `bank_account_id`
  wajib (rekening tujuan RAFA, sesuai PRD API contract).
- Referensi yang sama untuk invoice yang sama → ditolak (anti-duplikat);
  referensi berbeda → diizinkan (DP kedua, dst).
- Pembayaran hanya untuk invoice `ISSUED / UNPAID / PARTIALLY_PAID / OVERDUE`
  (belum lunas, bukan `PAID`/`CANCELLED`, bukan `DRAFT` belum diterbitkan).
- Tidak ada: auto-approval, refund, payment gateway.

## Arsitektur

| Komponen | Peran |
|---|---|
| `SubmitPaymentAction` | Validasi invoice & status, guard duplikat referensi, validasi file, simpan private + `Attachment` `PAYMENT_PROOF`, create `PENDING → SUBMITTED`, audit `PAYMENT_SUBMITTED` |
| `PaymentPolicy` | `create` = owner invoice (admin/owner boleh); `view` = owner/admin; `manage` (verifikasi, 10D) = admin; **tanpa delete** |
| `SubmitPaymentRequest` | Validasi input + `proof` via `FileSecurity::validationRules()` |
| `PaymentController` | `GET /invoices/{id}/payments`, `POST /invoices/{id}/payments`, `GET /invoices/{id}/payments/{payment}` |
| `PaymentResource` | Snapshot pembayaran + proof (private, `url:null`) |
| Migration `...000010` | `payments.sender_name`, `payments.reference` |

## Endpoints

```
POST /api/v1/invoices/{invoice}/payments   user/admin  submit (amount, payment_date, bank_account_id, sender_name, reference, proof)
GET  /api/v1/invoices/{invoice}/payments   owner/admin  list
GET  /api/v1/invoices/{invoice}/payments/{payment}  owner/admin  detail (proof)
```

## Test Result — `PaymentSubmissionTest` (6 kasus, 90 assertions)

- **Valid submission** → 201, status `SUBMITTED`, bukti tersimpan disk private
  (`url:null`), `paid_amount` invoice tetap 0, status invoice tidak berubah.
- **Invalid amount** (0 / negatif) → 422 field `amount`.
- **Wrong invoice & ownership** → non-owner 403; invoice tak ada 404.
- **Duplicate reference** → 409 `BUSINESS_RULE_VIOLATION`; referensi berbeda →
  201 (banyak payment 1 invoice).
- **Upload security** → proof hilang/bukan foto/oversize → 422 `proof`; tidak ada
  baris payment dibuat.
- **No auto-approval** → status `SUBMITTED`, `verified_by` & `rejection_reason`
  null.