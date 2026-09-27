# 05 — Payment Balance, Overpayment & Deadline (Phase 10E)

Penyempurnaan aturan saldo pembayaran, kelebihan bayar, dan batas waktu
pembayaran dari phase 10B–10D.

## Partial Payment

- **1 invoice → banyak payment yang di-approve** (tanpa batas jumlah).
- Saldo dihitung dari **total payment APPROVED** (`paid_amount` = `Σ approved`),
  bukan dari kecerobohan/pembulatan kolom — mencegah *double counting*.
- Invoice berstatus `PARTIALLY_PAID` sampai `paid_amount == grand_total` → `PAID`.
- Payment berikutnya (DP lanjutan) tetap mengacu invoice yang sama
  (`invoice_id` sama; `SubmitPaymentAction` mengizinkan status
  ISSUED/UNPAID/PARTIALLY_PAID/OVERDUE yang belum lunas).

## Overpayment

- Terjadi saat payment yang di-approve **melebihi total invoice**.
- **Tidak ada auto-refund**: kelebihan dicadangkan di `overpayment_amount`;
  invoice berstatus `OVERPAID` (menunggu tindakan manual).
- **Integration boundary untuk Phase 11 refund**: `VerifyPaymentAction` memanggil
  `RefundBoundary::noteOverpayment($invoice, $excess)` pada setiap overpayment;
  implementasi default (`DeferredRefundBoundary`) no-op sampai engine refund
  Phase 11 hadir.

## Deadline

- `deadline = invoice.issued_at + 24 jam` (`due_at` dihitung saat `IssueInvoiceAction`).
- **Rejection tidak mereset deadline** — `due_at` tidak pernah disentuh verifikasi.
- **Extension manual oleh Admin** jika policy mengizinkan:
  `ExtendInvoiceDeadlineAction`/`POST /invoices/{invoice}/extend-deadline`
  (hours 0–168; default 24). Hanya aksi ini yang boleh memutasi `due_at`;
  `issued_at` tetap sebagai sumber awal; perpanjangan wajib audit
  (`INVOICE_DEADLINE_EXTENDED`).
- Expired booking release tetap milik Phase 8 (`bookings:expire` pada
  `payment_deadline_at`); invoice OVERDUE di sini adalah yang tidak dibayar
  dalam 24 jam invoice (terpisah, didokumentasikan agar tidak terlebur).

## Arsitektur

| Komponen | Peran |
|---|---|
| `VerifyPaymentAction` | saldo berbasis `Σ approved payments` (anti double counting); panggil `RefundBoundary::noteOverpayment` saat excess > 0 |
| `RefundBoundary` / `DeferredRefundBoundary` | seam Phase 11 (no-op default) |
| `ExtendInvoiceDeadlineAction` | mutasi `due_at` satu-satunya yang sah; audit |
| `ExtendInvoiceDeadlineRequest` | admin; `hours` 0–168 |
| `routes/api.php` | `POST /invoices/{invoice}/extend-deadline` |

## Test Result — `PaymentBalanceDeadlineTest` (6 kasus, 99 assertions)

- **Multiple payments** → `paid_amount` == `Σ approved` == grand_total (800.000
  dari 250+250+300), status PAID, balance 0, overpayment 0.
- **Partial sequence** → PARTIALLY_PAID (balance 600k) → payment ditolak tidak
  menambah → payment lanjutan → PAID; hanya APPROVED yang dihitung.
- **Overpayment** → OVERPAID + `overpayment_amount` 200k + boundary
  `noteOverpayment` terpanggil sekali dengan excess tepat.
- **Deadline** → `due_at == issued_at + 24h`; reject tidak mengubah `due_at`.
- **Extension** → +48h dari due_at lama; `issued_at` tetap; audit
  `INVOICE_DEADLINE_EXTENDED`.
- **Otorisasi & status** → user 403; `hours:999` → 422; invoice PAID → 409
  `INVALID_STATE_TRANSITION`.