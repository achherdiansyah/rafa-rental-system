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
| **10D** | Payment Verification (approve/reject Admin, settlement PAID/PARTIAL/OVERPAID) | Selesai (`451d5ba`) |
| **10E** | Balance, Overpayment & Deadline (extension manual, anti double counting, refund seam) | Selesai (`7cf81df`) |
| **10F** | Invoice & Payment UI (User + Admin queue/verification/proof) | Selesai (`8079bcb`) |
| **10G** | Integration Testing & Review (E2E flow, race/pessimistic lock, N+1, security) | Selesai (Aktif) |
| 10H | Final Review & Git Merge | Pending |

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
- `04-payment-verification.md`: Spesifikasi verifikasi Admin (`SUBMITTED →
  APPROVED | REJECTED`), settlement exact → PAID / partial → PARTIALLY_PAID /
  overpayment → OVERPAID, rejection wajib reason, deadline tidak pernah direset.
- `05-payment-balance-deadline.md`: Spesifikasi saldo berbasis total approved
  payment (anti double counting), cadangan overpayment + seam refund Phase 11,
  deadline `issued_at + 24 jam`, extension manual wajib audit, reject tidak
  mereset deadline.
- `06-invoice-payment-ui.md`: Spesifikasi UI invoice & pembayaran — portal USER
  (list/detail, saldo, riwayat, upload bukti, notice overpayment) dan ADMIN
  (payment queue, pratinjau bukti, verifikasi/tolak, indikator overpayment,
  extension deadline); backend `GET /payments` + `GET /payments/{id}/proof`.
- `07-integration-test-report.md`: Laporan integrasi end-to-end billing,
  invoice, deadline, payment & verifikasi (374 backend tests); review duplicate
  balance, race condition (pessimistic lock), N+1, mutasi riwayat keuangan,
  keamanan upload, dan akses finansial tak sah.