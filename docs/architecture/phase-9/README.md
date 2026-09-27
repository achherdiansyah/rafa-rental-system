# Phase 9: Rental Lifecycle & Timesheet — RAFA Rental System

Dokumentasi arsitektur pelaksanaan sewa lapangan (*Rental Execution*), siklus hidup rental, operasional timesheet harian, dan manajemen dokumen BAST.

---

## 1. Tujuan Phase 9

Mengoperasionalkan armada yang telah disetujui & di-assign pada Phase 8 menjadi eksekusi lapangan nyata:
- **Rental Core (9A):** Pembuatan rental dari booking `CONFIRMED` + unit fisik yang sudah di-assign; lifecycle `ASSIGNED → DISPATCHED → ARRIVED → ONGOING → DEMOBILIZING → RETURN_INSPECTED → COMPLETED`; sinkronisasi status unit fisik; audit.
- **Timesheet & BAST (lanjutan):** Catatan jam kerja harian per unit, revisi timesheet, dan serah-terima check-in/check-out.

---

## 2. Struktur Subphase

| Subphase | Fokus | Status |
|---|---|---|
| **9A** | Rental Core (relasi booking, rental_details, state machine, physical unit sync) | Selesai (Aktif) |
| 9B | Timesheet Submission | Pending |
| 9C | Timesheet Revision & Approval | Pending |
| 9D | BAST Check-in / Check-out | Pending |
| 9E | Integration Testing & Quality Gate | Pending |

---

## 3. Daftar Dokumen

- `01-rental-core.md`: Spesifikasi domain rental (`rentals`, `rental_details`), relasi booking & assignment, state machine lifecycle, dan sinkronisasi status unit fisik.