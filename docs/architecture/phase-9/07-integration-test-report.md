# 07 — Rental & Timesheet Integration Test Report (Phase 9G)

Laporan verifikasi end-to-end siklus rental & timesheet, kuota kualitas, dan hasil review.

## 1. End-to-End Flow Yang Diuji

`RentalTimesheetIntegrationTest` (3 kasus, 99 assertions) menjalankan seluruh alur:

```
CONFIRMED
 → DISPATCHED      (unit MOBILIZING)
 → ARRIVED         (unit ON_SITE)
 → ONGOING         (BAST check-in, started_at)
 → Timesheet       (user catat; total_work_hours dihitung server)
 → User/PIC Signature (private storage, signature_reference tercatat)
 → Admin Validation (approve → revise append-only → re-approve)
 → RETURNING       (unit DEMOBILIZING, TIDAK available)
 → INSPECTION      (unit RETURN_INSPECTION)
 → AVAILABLE / MAINTENANCE / DAMAGED
```

Test tambahan mencakup:
- **Invalid transition** — `start` dari ASSIGNED → 409 `INVALID_STATE_TRANSITION`; `ready` sebelum INSPECTION → 409.
- **Unauthorized action** — USER memanggil `arrive`/`return` → 403; validasi hanya ADMIN.
- **Timesheet revision** — approve → revise (end_hm 16→16.5, break 60→30, jam kerja 7→8) → re-approve; entri revisi tetap utuh (v1, old_start/old_end benar), tidak ada data hilang.
- **Signature** — unggah png → 200, `document_type=TIMESHEET_SIGNATURE`, URL `null` (private), `signature_reference` terisi, attachment terhubung ke timesheet, file ada di disk `local` fake.
- **Inspection** — MAINTENANCE → unit `MAINTENANCE`; DAMAGED → unit `MAINTENANCE` (tanpa charge otomatis); READY → unit `AVAILABLE`, `inspection_result`/`condition_notes`/`checked_out_at` tersimpan per detail.
- **Unit status consistency** — status fisik unit dicek di SETIAP transisi (MOBILIZING → ON_SITE → DEMOBILIZING → RETURN_INSPECTION → AVAILABLE/MAINTENANCE).
- **Booking/rental relationship** — `rental.booking_id` = id booking sumber; `rental_detail.assignment_id` = id assignment yang dipilih (urutan tak bergantung).
- **Audit** — spy log memverifikasi seluruh event: `RENTAL_CREATED/DISPATCHED/ARRIVED/ONGOING/DEMOBILIZING/RETURN_INSPECTED/INSPECTION` + `TIMESHEET_CREATED/SUBMITTED/APPROVED`.

## 2. Hasil Eksekusi

| Command | Hasil |
|---|---|
| `php artisan test` | **334 passed (1362 assertions)** |
| `./vendor/bin/pint --test` | **passed** |
| `npm run test` (vitest) | **94 passed (22 files)** |
| `npm run build` (tsc -b + vite) | **sukses** |
| Playwright | tidak tersedia di repo (skipped) |

## 3. Review

### N+1 — DIPERBAIKI
- `TimesheetController::index/show` kini eager-load `attachments` (signature tampil di list tanpa query per baris).
- Semua action timesheet (Create/Submit/Validate/Revise) ikut memuat `attachments`.
- `RentalController` & `TransitionRentalAction` memuat `details.assignment.unit.model` sekali (hapus key duplikat).

### Duplicate API request — TIDAK ADA
- UI reload data sekali per aksi via `Promise.all` (`UserTimesheetsPage.loadAll`); tidak ada request ganda/redundan.

### State inconsistency — TIDAK DITEMUKAN
- Status unit selalu sinkron per transisi (dicek assertion per langkah).
- Unit tidak pernah langsung `AVAILABLE` pasca return.

### Missing audit — TIDAK DITEMUKAN
- Setiap transisi rental/timesheet memproduksi event audit; diuji eksplisit.

### Lost revision history — TIDAK DITEMUKAN
- Riwayat revisi append-only; snapshot lama tetap utuh setelah koreksi + resubmit + approval ulang (dicek count & nilai lama).

## 4. Files

- `tests/Feature/Rental/RentalTimesheetIntegrationTest.php` — E2E lifecycle + maintenance/damaged + audit.
- `app/Http/Controllers/Api/V1/{RentalController,TimesheetController}.php` — perbaikan eager loading (N+1).
- `app/Actions/Timesheet/*` — tambah eager `attachments` pada response action.
- Docs: `docs/architecture/phase-9/07-integration-test-report.md`.