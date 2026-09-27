# 01 — Refund Core (Phase 11A)

Domain refund manual via transfer bank (tanpa payment gateway). Sumber:
pembatalan setelah pembayaran dan kelebihan bayar (overpayment).

## State Machine

```text
PENDING ──process──> PROCESSING ──complete──> COMPLETED
   │                       │
   └──fail──> FAILED ──────┘
```

| Status | Makna |
|---|---|
| `PENDING` | Terdaftar dari sumber (cancellation/overpayment), menunggu tindakan staf |
| `PROCESSING` | Staf keuangan menyiapkan transfer (bank tujuan, referensi) |
| `COMPLETED` | Transfer selesai; bukti terlampir |
| `FAILED` | Gagal (rekening invalid, dst); riwayat tetap tersimpan |

## Sumber Refund

- **Cancellation setelah payment** — `RefundRegistrationService::registerPendingRefund`
  membuat refund `PENDING` per invoice yang punya payment APPROVED (nilai = total
  approved). Tidak ada refund bila belum ada pembayaran.
- **Overpayment** — `RefundRegistrationService::noteOverpayment` dipanggil
  `VerifyPaymentAction` saat excess > 0 → refund `PENDING` sebesar kelebihan.
- Anti-duplikat: satu refund aktif (non-FAILED) per (invoice, source).

## Aturan

- `PENDING → PROCESSING` mencatat `customer_bank_info`, `transfer_reference`,
  `processed_by`, `processed_at` (manual via transfer bank).
- `PROCESSING → COMPLETED` mewajibkan **upload bukti transfer** (`REFUND_PROOF`)
  private disk + `completed_at`.
- `PENDING | PROCESSING → FAILED` wajib `failure_reason`.
- Transisi ilegal ditolak (`InvalidStateTransitionException`).
- **Histori finansial tidak dihapus** — tanpa endpoint delete.
- **Audit setiap perubahan status**: `REFUND_PROCESSING`, `REFUND_COMPLETED`,
  `REFUND_FAILED` (+ `PENDING` saat registrasi).
- WhatsApp/notifikasi BELUM diimplementasikan (subphase berikutnya).

## Arsitektur

| Komponen | Peran |
|---|---|
| `RefundRegistrationService` (implements `RefundBoundary`) | Sumber refund + anti-duplikat; menggantikan boundary no-op |
| `RefundLifecycleService` | `process` / `complete` / `fail` + audit + `Gate::forUser(actor)` |
| `RefundPolicy` | `view` owner/admin; `manage` admin (tanpa delete) |
| `RefundController` | `GET /refunds`, `GET /refunds/{id}`, `POST /{id}/process|complete|fail` |
| `Process/Complete/FailRefundRequest` | Validasi (bank info, bukti wajib via `FileSecurity`, alasan min 5) |
| Migration `...000011` | `source`, `transfer_reference`, `failure_reason`, `completed_at`, `customer_bank_info` nullable |
| Enum `RefundStatus`/`RefundSource` | `PENDING|PROCESSING|COMPLETED|FAILED`; `CANCELLATION|OVERPAYMENT` |

Perubahan enum: PRD lama `REQUESTED/REVIEWED/APPROVED/REJECTED/PROCESSING/COMPLETED`
dipetakan ke set Phase 11 (`PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`); `EnumTest`,
`RefundFactory`, dan test 10D/10G disesuaikan (overpayment kini mendaftarkan refund
PENDING — tidak lagi no-refund).

## Test Result — `RefundCoreTest` (7 kasus, 32 assertions)

- Overpayment → refund PENDING sekali (idempotent), amount = excess.
- Cancellation → refund per invoice bernilai Σ approved; tanpa pembayaran → none.
- Lifecycle processing → attributes + transisi ilegal (PENDING langsung complete).
- Complete → COMPLETED + proof private (`REFUND_PROOF`) + audit.
- Fail dari PENDING/PROCESSING; terminal.
- Authorization & scoping (view owner/admin; manage admin; API process→complete).

## Files

- `app/Services/Refund/{RefundRegistrationService,RefundLifecycleService}.php`
- `app/{Enums/RefundStatus,Enums/RefundSource,Models/Refund,Policies/RefundPolicy}`
- `app/Http/Controllers/Api/V1/RefundController.php`, `app/Http/Requests/Refund/*`
- `app/Http/Resources/RefundResource.php`
- migration `2026_09_25_000011_add_refund_core_fields_table.php`
- `tests/Feature/Refund/RefundCoreTest.php`