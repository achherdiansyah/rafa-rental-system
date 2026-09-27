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
| **9B** | Dispatch, Arrival & Ongoing (konfirmasi operasional + UI Admin) | Selesai (`0cd5393`) |
| **9C** | Timesheet Backend (jam kerja harian, kalkulasi, submit) | Selesai (`4db278c`) |
| **9D** | Signature, Validasi Admin & Revisi Timesheet (append-only) | Selesai (`16a469c`) |
| **9E** | Return & Inspection (pengembalian unit, keputusan kondisi, mark ready) | Selesai (Aktif) |
| 9F | BAST Check-in / Check-out | Pending |

---

## 3. Daftar Dokumen

- `01-rental-core.md`: Spesifikasi domain rental (`rentals`, `rental_details`), relasi booking & assignment, state machine lifecycle, dan sinkronisasi status unit fisik.
- `02-rental-lifecycle.md`: Spesifikasi transisi awal rental (Dispatch → Arrival → Ongoing), guard transisi ilegal, konfirmasi Admin, dan UI Admin.
- `03-timesheet-backend.md`: Spesifikasi pencatatan timesheet harian, kalkulasi jam kerja aktual, validasi, dan pengajuan validasi Admin.
- `04-timesheet-validation-revision.md`: Spesifikasi tanda tangan (private storage), validasi/penolakan Admin, koreksi dengan riwayat revisi append-only.
- `05-return-inspection.md`: Spesifikasi pengembalian unit, inspeksi Admin, keputusan kondisi unit (READY/MAINTENANCE/DAMAGED), dan larangan unit AVAILABLE langsung setelah return.