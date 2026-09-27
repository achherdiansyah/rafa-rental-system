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
| **11A** | Refund Core (domain, sumber cancellation/overpayment, lifecycle PENDING→PROCESSING→COMPLETED/FAILED, audit) | Selesai (Aktif) |
| 11B | Refund UI (User + Admin) | Pending |
| 11C | Outstanding | Pending |
| 11D | Notification | Pending |
| 11E | Integration Testing & Final Review | Pending |

---

## 3. Daftar Dokumen

- `01-refund-core.md`: Spesifikasi domain refund — sumber (pembatalan setelah
  pembayaran, overpayment), state `PENDING → PROCESSING → COMPLETED / FAILED`,
  transfer manual via bank, bukti privat, actor & timestamp, audit, larangan
  hapus histori. Notifikasi/WhatsApp ditunda.