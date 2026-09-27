# 06 — Rental & Timesheet UI (Phase 9F)

Antarmuka pengguna untuk seluruh siklus rental & timesheet pada portal USER dan ADMIN.
Tidak menyentuh invoice/payment/refund (masih placeholder per PRD).

## Ruang Lingkup

### User / PIC (`/app`)

- **`/app/rentals` (UserRentalsPage)** — melihat seluruh rental milik akun: `booking_code`, lokasi proyek, status rental (badge warna per state), daftar unit (serial + plat), waktu mulai. Hanya data milik user sendiri (backend memfilter otomatis).
- **`/app/timesheets` (UserTimesheetsPage)**:
  - **Isi timesheet**: form (unit rental yang sedang `ONGOING`, tanggal laporan ≤ hari ini, HM awal/akhir, break, standby, breakdown, operator, catatan). Jam kerja dihitung server-side.
  - **Ajukan validasi / Ajukan ulang** (DRAFT & REJECTED → SUBMITTED).
  - **Tanda tangan**: unggah gambar (PNG/JPEG/WebP) → simpan di private storage.
  - **Histori**: status tiap entry + riwayat revisi append-only (versi, perubahan HM, alasan, pembuat).

### Admin / Staff (`/admin`)

- **`/admin/rentals` (AdminRentalsPage)** — monitoring + eksekusi: Dispatch → Arrival → Ongoing → **Return** → **Inspection** → **Isi Hasil Inspeksi** (READY/MAINTENANCE/DAMAGED + catatan). Mark READY melepas unit kembali AVAILABLE.
- **`/admin/timesheets` (AdminTimesheetsPage)** — filter status (default "Menunggu Validasi"):
  - **Validate**: Setujui (SUBMITTED → APPROVED).
  - **Reject**: tolak dengan alasan wajib ≥5 karakter (tersimpan sebagai entri revisi `REJECTED: …`).
  - **Correction/Revision**: koreksi isi timesheet APPROVED (snapshot append-only, kembali ke SUBMITTED).
  - Riwayat revisi per entry.

## UI Wajib (wajib ada di kedua portal)

- **Loading** — skeleton cards saat memuat.
- **Empty** — EmptyState saat tidak ada data.
- **Error** — Alert banner untuk kegagalan load; toast untuk kegagalan aksi.
- **Validation** — validasi client-side (range HM, alasan min 5 karakter, tanggal) + error server tampil via toast/api error.
- **Confirmation** — ConfirmDialog untuk semua aksi destruktif/komit (submit, approve, reject, revise, transisi rental, hasil inspeksi).
- **Status badge** — warna konsisten: DRAFT/ASSIGNED neutral, SUBMITTED/DISPATCH/ARRIVED/DEMOBILIZING/RETURN_INSPECTED warning, APPROVED/ONGOING/COMPLETED success, REJECTED/CANCELLED danger, MAINTENANCE amber, DAMAGED red.
- **Responsive** — grid `sm/lg`, card stack vertikal di mobile.
- **No full page reload** — SPA axios; semua aksi memuat ulang data secara lokal (tanpa refresh).

## Aliran State (terikat backend)

```
              User                           Admin
        ┌──────────────┐              ┌──────────────────────┐
  enter │ DRAFT ──────►│ SUBMITTED ──►│ ─┬─ approve → APPROVED│
timesheet│  ▲   submit  │  (reject note) │—— reject → REJECTED │
        │  └─ ajukan ulang (REJECTED → SUBMITTED)             │
        └──────────────┘              └— revise(APPROVED→SUBMITTED, append-only)
```

Rental: `ASSIGNED → DISPATCHED → ARRIVED → ONGOING → DEMOBILIZING → RETURN_INSPECTED → COMPLETED`
(keputusan unit saat `ready`: READY → AVAILABLE; MAINTENANCE/DAMAGED → MAINTENANCE, tanpa billing otomatis).

## Backend Penyesuaian

- `SubmitTimesheetAction`: kini menerima `DRAFT` **dan** `REJECTED` → `SUBMITTED` (resubmit setelah penolakan).

## Files

- `frontend/src/features/rental/pages/UserRentalsPage.tsx`
- `frontend/src/features/timesheet/{pages/UserTimesheetsPage.tsx, pages/AdminTimesheetsPage.tsx, services/timesheetService.ts}`
- `frontend/src/types/timesheet.ts`
- `frontend/src/components/layout/UserLayout.tsx` (nav baru), `routes/AppRoutes.tsx`
- `backend/app/Actions/Timesheet/SubmitTimesheetAction.php`
- Test: `frontend/src/features/timesheet/{UserTimesheetsPage,AdminTimesheetsPage}.test.tsx`, `backend/tests/Feature/Timesheet/TimesheetValidationTest.php` (resubmit)

## Test Result

- Frontend: **94 tests (22 files)** — alur kritis user (lihat, isi, submit, tanda tangan, riwayat), admin (approve, reject, revise), empty/loading.
- Backend: **331 tests (1263 assertions)** — termasuk resubmit REJECTED → SUBMITTED.