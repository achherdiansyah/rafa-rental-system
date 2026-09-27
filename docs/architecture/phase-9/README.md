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
| **9A** | Rental Core (relasi booking, rental_details, state machine, physical unit sync) | Selesai (`5677f9c`) |
| **9B** | Dispatch, Arrival & Ongoing (konfirmasi operasional + UI Admin) | Selesai (Aktif) |
| 9C | Timesheet Submission | Pending |
| 9D | Timesheet Revision & Approval | Pending |
| 9E | BAST Check-in / Check-out | Pending |
| 9F | Integration Testing & Quality Gate | Pending |

---

## 3. Daftar Dokumen

- `01-rental-core.md`: Spesifikasi domain rental (`rentals`, `rental_details`), relasi booking & assignment, state machine lifecycle, dan sinkronisasi status unit fisik.
- `02-rental-lifecycle.md`: Spesifikasi transisi awal rental (Dispatch → Arrival → Ongoing), guard transisi ilegal, konfirmasi Admin, dan UI Admin.