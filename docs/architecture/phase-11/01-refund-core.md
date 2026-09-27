# 01 — Refund Core (Phase 11A) & Approval/Settlement (Phase 11B)

Domain refund manual via transfer bank (tanpa payment gateway). Sumber:
pembatalan setelah pembayaran dan kelebihan bayar (overpayment).

## State Machine

```text
PENDING ──approve (OWNER)──> APPROVED ──process (ADMIN)──> PROCESSING
   │                            │                             │
   └────────────fail────────┴──────────fail─┴──complete──> COMPLETED
                                      └────────fail────────> FAILED
```

| Status | Makna |
|---|---|
| `PENDING` | Terdaftar dari sumber, menunggu persetujuan Owner |
| `APPROVED` | Disetujui Owner (`approved_by`, `approved_at`, `approval_reason`) — pra-syarat PROCESSING |
| `PROCESSING` | Staf keuangan menyiapkan transfer (bank tujuan, referensi) |
| `COMPLETED` | Transfer selesai; bukti terlampir |
| `FAILED` | Gagal (wajib `failure_reason`); riwayat tetap tersimpan |

## Approval & Settlement (11B)

- **Permission (Phase 1 matrix)**: approval = **OWNER** (`approve-refund`,
  `RefundPolicy::approve`); proses/komplet/gagal = **ADMIN** (`manage`).
  Admin TIDAK boleh approve; Owner TIDAK boleh process.
- **PROCESSING hanya setelah APPROVED** — `process()` di guard dari status
  selain `APPROVED` (409).
- **Validasi nominal terhadap sumber refund**: `RefundLifecycleService::validBase()`
  — OVERPAYMENT → `invoice.overpayment_amount`; CANCELLATION → Σ payments
  APPROVED. `approve` & `process` menolak bila `amount > base` atau base ≤ 0
  (`BusinessRuleException`); **refund tidak pernah melebihi dasar yang valid**.
- **COMPLETED hanya setelah bukti transfer dicatat** — `REFUND_PROOF` privat
  wajib (`FileSecurity`); `completed_at` di-set.
- **FAILED menyimpan alasan** (`failure_reason` wajib min 5).
- **Nominal tidak pernah diubah** oleh transisi mana pun (tiada mutasi `amount`).
- **Actor/waktu/reason/transfer/proof tercatat**: `approved_by/approved_at/
  approval_reason`, `processed_by/processed_at`, `transfer_reference`,
  `failure_reason`, attachment bukti.

## Sumber Refund

- **Cancellation setelah payment** — `RefundRegistrationService::registerPendingRefund`
  membuat refund `PENDING` per invoice yang punya payment APPROVED (nilai = total
  approved). Tidak ada refund bila belum ada pembayaran.
- **Overpayment** — `RefundRegistrationService::noteOverpayment` dipanggil
  `VerifyPaymentAction` saat excess > 0 → refund `PENDING` sebesar kelebihan.
- Anti-duplikat: satu refund aktif (non-FAILED) per (invoice, source).

## Aturan Transisi (11B)

- `PENDING → APPROVED` oleh **OWNER** (mencatat `approved_by`, `approved_at`,
  `approval_reason`); validasi nominal ≤ dasar refund.
- `APPROVED → PROCESSING` oleh **ADMIN** (mencatat `customer_bank_info`,
  `transfer_reference`, `processed_by`, `processed_at`); validasi nominal lagi.
- `PROCESSING → COMPLETED` oleh **ADMIN** (wajib bukti `REFUND_PROOF` privat +
  `completed_at`).
- `PENDING | APPROVED | PROCESSING → FAILED` wajib `failure_reason`.
- Transisi ilegal ditolak (`InvalidStateTransitionException`, 409).
- **Histori finansial tidak dihapus** — tanpa endpoint delete.
- **Audit setiap perubahan status**: `REFUND_APPROVED`, `REFUND_PROCESSING`,
  `REFUND_COMPLETED`, `REFUND_FAILED`.
- WhatsApp/notifikasi BELUM diimplementasikan (subphase berikutnya).

## Arsitektur

| Komponen | Peran |
|---|---|
| `RefundRegistrationService` (implements `RefundBoundary`) | Sumber refund + anti-duplikat |
| `RefundLifecycleService` | `approve` / `process` / `complete` / `fail` + `validBase()` + audit + `Gate::forUser(actor)` |
| `RefundPolicy` | `view` owner/admin; `approve` owner; `manage` admin (tanpa delete) |
| `RefundController` | `GET /refunds`, `GET /refunds/{id}`, `POST /{id}/approve|process|complete|fail` |
| `Approve/Process/Complete/FailRefundRequest` | Validasi per status & permission |
| Migration `...000011`, `...000012` | kolom lifecycle + approval (`approval_reason`, `approved_by`, `approved_at`) |
| Enum `RefundStatus`/`RefundSource` | `PENDING|APPROVED|PROCESSING|COMPLETED|FAILED`; `CANCELLATION|OVERPAYMENT` |

Perubahan enum: PRD lama `REQUESTED/REVIEWED/APPROVED/REJECTED/PROCESSING/COMPLETED`
dipetakan ke set Phase 11 (`PENDING`, `APPROVED`, `PROCESSING`, `COMPLETED`,
`FAILED`); `EnumTest`, `RefundFactory`, dan test 10D/10G disesuaikan (overpayment
kini mendaftarkan refund PENDING — tidak lagi no-refund).

## Test Result — `RefundCoreTest` (9 kasus, 47 assertions)

- Overpayment → refund PENDING sekali (idempotent), amount = excess.
- Cancellation → refund per invoice bernilai Σ approved; tanpa pembayaran → none.
- Processing hanya setelah approval (PENDING langsung process → 409).
- Approve → APPROVED (actor/waktu/alasan) → process → PROCESSING; audit.
- Complete → COMPLETED + proof private (`REFUND_PROOF`) + audit.
- Fail dari PENDING/APPROVED/PROCESSING; terminal.
- Authorization: owner approve & admin manage terpisah; admin tidak boleh approve,
  owner tidak boleh process; proses-tanpa-approval 409; alur API penuh.
- Refund tidak boleh melebihi dasar yang valid (BUSINESS_RULE_VIOLATION).

## Files

- `app/Services/Refund/{RefundRegistrationService,RefundLifecycleService}.php`
- `app/{Enums/RefundSource,Models/Refund,Policies/RefundPolicy}`, `app/Enums/RefundStatus.php`
- `app/Http/Controllers/Api/V1/RefundController.php`, `app/Http/Requests/Refund/*`
- `app/Http/Resources/RefundResource.php`
- migration `2026_09_25_000011_add_refund_core_fields_table.php`,
  `2026_09_25_000012_add_refund_approval_fields_table.php`
- `tests/Feature/Refund/RefundCoreTest.php`