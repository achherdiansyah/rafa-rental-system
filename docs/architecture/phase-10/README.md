# Phase 10: Billing, Invoice & Payment — RAFA Rental System

Dokumentasi arsitektur penagihan sewa berbasis jam aktual, penerbitan invoice,
dan alur pembayaran.

---

## 1. Tujuan Phase 10

Menutup siklus usaha setelah eksekusi rental (Phase 9): menghitung tagihan dari
jam kerja aktual + tarif snapshot + MOB/DEMOB per unit fisik, menerbitkan
invoice, dan menangani pembayaran (partial, overpayment, refund manual).

---

## 2. Struktur Subphase

| Subphase | Fokus | Status |
|---|---|---|
| **10A** | Billing Engine (actual hours × hourly rate, snapshot, MOB/DEMOB per unit, no rounding) | Selesai (`4a76844`) |
| **10B** | Invoice Creation & Lifecycle (DRAFT→ISSUED→UNPAID→OVERDUE, snapshot, numbering, PDF) | Selesai (`2bd37ba`) |
| **10C** | Payment Submission (PAYMENT_PENDING→SUBMITTED, proof private, anti-duplikat) | Selesai (Aktif) |
| 10D | Payment Validation (approve/reject) | Pending |
| 10E | Refund & Outstanding | Pending |
| 10F | Integration Testing & Final Review | Pending |

---

## 3. Daftar Dokumen

- `01-billing-engine.md`: Spesifikasi mesin penagihan `RentalBillingService`;
  aturan jam aktual, tarif snapshot per line, skema All-in/Non All-in, MOB/DEMOB
  per unit fisik (dapat berbeda), no tax/discount/overtime, no rounding, dan
  catatan PBD actual hours > 8 tanpa perilaku rekaan.
- `02-invoice-lifecycle.md`: Spesifikasi siklus hidup invoice
  (`DRAFT → ISSUED → UNPAID → OVERDUE`, void → CANCELLED), snapshot
  `invoice_details`, penomoran `INV/YYYYMM/XXXX`, deadline 24 jam dari
  penerbitan, banyak invoice per booking, dan PDF tanpa dependensi.
- `03-payment-submission.md`: Spesifikasi pengajuan pembayaran user
  (`PAYMENT_PENDING → SUBMITTED`), bukti transfer privat sesuai spesifikasi
  keamanan, anti-duplikat referensi, dan larangan auto-approval.