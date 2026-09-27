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
| **9F** | Rental & Timesheet UI (portal USER + ADMIN, validasi, revisi, inspeksi) | Selesai (`625336e`) |
| **9G** | Integration Testing & Review (E2E lifecycle, audit, N+1, revision) | Selesai (`ca6b56d`) |
| **9H** | Final Review, Quality Gate & Git Merge ke `main` | Selesai (merge), READY FOR PHASE 10 |

---

## 4. Final Review 9A–9G (Phase 9H)

| Checklist PRD | Status | Bukti |
|---|---|---|
| `CONFIRMED → DISPATCHED → ARRIVED → ONGOING` | ✔ | Transition map + `RentalTimesheetIntegrationTest` |
| `DISPATCHED` ≠ `ONGOING` | ✔ | Enum `RentalStatus` terpisah; transisi opersional per-langkah (lompat ditolak 409) |
| Admin mengonfirmasi operational start | ✔ | `/rentals/{id}/start` hanya `RentalPolicy::operate` (ADMIN); USER 403 |
| Timesheet berdasarkan actual hours | ✔ | `total_work_hours = (end_hm - start_hm) - break` dihitung server-side |
| Break berupa duration | ✔ | `break_minutes` (menit) |
| No rounding | ✔ | `round()` dihapus; presisi diserahkan ke kolom `DECIMAL(8,2)` (Create & Revise) |
| Historis revisi tidak hilang | ✔ | append-only `timesheet_revisions`; snapshot lama utuh pasca koreksi/re-submit (dicek di 9G) |
| User/PIC signature | ✔ | `POST /timesheets/{id}/signature` → private storage; `url:null` |
| Admin validation | ✔ | approve/reject (reason wajib) di bawah `validate` (ADMIN) |
| return → inspection | ✔ | `ONGOING → DEMOBILIZING → RETURN_INSPECTED`; unit tidak AVAILABLE |
| Unit hanya AVAILABLE setelah inspection | ✔ | hanya hasil `READY` yang melepas unit ke `AVAILABLE` |
| Damage tidak jadi charge otomatis | ✔ | `DAMAGED` hanya tercatat + unit → MAINTENANCE; tanpa invoice/charge |
| Audit tersedia | ✔ | `AuditLogger` di seluruh aksi lifecycle; diverifikasi spy di 9G |
| Invoice / payment / refund TIDAK diimplementasi | ✔ | halaman frontend placeholder; tidak ada endpoint/halaman baru |

### Quality Gate 9H

| Command | Hasil |
|---|---|
| `php artisan test` | **334 passed (1362 assertions)** |
| `./vendor/bin/pint --test` | passed |
| `npm run test` | **94 passed (22 files)** |
| `npm run build` (tsc -b + vite) | sukses |
| `npm run lint` | tanpa error (warning pola lama) |
| Static analysis (larastan/phpstan) | tidak tersedia di repo |

---

## 3. Daftar Dokumen

- `01-rental-core.md`: Spesifikasi domain rental (`rentals`, `rental_details`), relasi booking & assignment, state machine lifecycle, dan sinkronisasi status unit fisik.
- `02-rental-lifecycle.md`: Spesifikasi transisi awal rental (Dispatch → Arrival → Ongoing), guard transisi ilegal, konfirmasi Admin, dan UI Admin.
- `03-timesheet-backend.md`: Spesifikasi pencatatan timesheet harian, kalkulasi jam kerja aktual, validasi, dan pengajuan validasi Admin.
- `04-timesheet-validation-revision.md`: Spesifikasi tanda tangan (private storage), validasi/penolakan Admin, koreksi dengan riwayat revisi append-only.
- `05-return-inspection.md`: Spesifikasi pengembalian unit, inspeksi Admin, keputusan kondisi unit (READY/MAINTENANCE/DAMAGED), dan larangan unit AVAILABLE langsung setelah return.
- `06-rental-timesheet-ui.md`: Spesifikasi antarmuka USER & ADMIN untuk rental/timesheet: pencatatan, tanda tangan, validasi, koreksi/revisi, inspeksi, dan komponen UI wajib.
- `07-integration-test-report.md`: Laporan integrasi end-to-end rental & timesheet (334 backend tests), hasil review N+1, state consistency, audit, dan riwayat revisi.