# Phase 11: Refund, Outstanding & Notification — RAFA Rental System

Dokumentasi domain refund manual, outstanding, dan notifikasi/komunikasi.

---

## 1. Tujuan Phase 11

Menyelesaikan siklus keuangan pasca-pembayaran: eksekusi refund manual
(transfer bank), pengelolaan outstanding (sisa/kelebihan yang menunggu), dan
layanan notifikasi.

---

## 2. Struktur Subphase

| Subphase | Fokus | Status |
|---|---|---|
| **11A** | Refund Core (domain, sumber cancellation/overpayment, lifecycle PENDING→PROCESSING→COMPLETED/FAILED, audit) | Selesai |
| **11B** | Refund Approval & Settlement (OWNER approve, base validation, actor/reason/proof, audit) | Selesai |
| **11C** | Outstanding & Customer Credit Control (per-customer, invoice-based, approved-payment, no N+1) | Selesai |
| **11D** | In-App Notification (database channel, event + related entity, read state, API) | Selesai |
| **11E** | WhatsApp Notification Integration (pluggable provider, delivery audit, no fake success) | Selesai |
| **11F** | Refund, Outstanding & Notification UI (User + Admin/Owner, role-gated) | Selesai (Aktif) |
| 11G | Integration Testing & Final Review | Pending |

---

## 3. Daftar Dokumen

- `01-refund-core.md`: Spesifikasi domain refund — sumber (pembatalan setelah
  pembayaran, overpayment), state `PENDING → APPROVED (OWNER) → PROCESSING →
  COMPLETED / FAILED`, validasi nominal terhadap dasar refund, transfer manual
  via bank, bukti privat, actor & timestamp, audit, larangan hapus histori.
  Notifikasi/WhatsApp ditunda.
- `02-outstanding-credit-control.md`: Spesifikasi outstanding per customer dari
  invoice `ISSUED/UNPAID/PARTIALLY_PAID/OVERDUE`, basis approved payment,
  independen status booking, tanpa threshold rekaan, dan guard N+1.
- `03-in-app-notification.md`: Spesifikasi notifikasi in-app — channel database
  Laravel, event + related entity, state unread/read, scoping per user
  (ADMIN/OWNER sesuai target), API list/unread-count/mark-read, tanpa Redis.
- `04-whatsapp-notification.md`: Spesifikasi integrasi WhatsApp — provider
  pluggable (`WhatsAppGateway`), pemisahan business event vs channel, log
  delivery `SENT/FAILED/SKIPPED`, queue database opsional, tanpa fake success,
  sistem normal bila provider belum dikonfigurasi; reminder outstanding.
- `05-refund-outstanding-notification-ui.md`: Spesifikasi UI refund, outstanding
  & notification — user (status/history, saldo, pusat notifikasi), admin/owner
  (antrean & verifikasi refund, rekap pelanggan, monitor delivery), guard
  finansial & role, komponen reusable, tanpa duplicate request.