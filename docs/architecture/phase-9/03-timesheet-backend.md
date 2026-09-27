# Spesifikasi Timesheet Backend (Phase 9C) - RAFA Rental System

Dokumentasi implementasi pencatatan jam kerja harian operator per unit fisik yang sedang beroperasi (rental `ONGOING`), perhitungan jam kerja aktual tanpa pembulatan arbitrer, dan pengajuan untuk validasi Admin.

---

## 1. Aturan & Alur

```text
Operator (atau admin) mencatat harian
   │
   ▼
POST /api/v1/timesheets ──► DRAFT (jam kerja dihitung server-side)
   │
   ▼
POST /{id}/submit ──► SUBMITTED (menunggu validasi Admin)
```

- **Operator mengisi** — `start_hm`, `end_hm`, `break_minutes`, `operator_name`, `notes`, `signature_reference`.
- **User/PIC project menandatangani** — `signature_reference` (referensi tanda tangan / bukti).
- **Admin memvalidasi** — timesheet `SUBMITTED` siap diverifikasi (approval di fase lanjutan).
- **Actual hours disimpan** — `total_work_hours = (end_hm − start_hm) − (break_minutes/60)`, presisi `DECIMAL(8,2)`, **tanpa pembulatan menyeluruh** (rounding hanya ke 2 desimal penyimpanan).
- **Tidak membuat invoice / overtime logic** (defer ke fase billing).

---

## 2. Data (timesheets)

Field mengikuti Data Dictionary + spesifikasi customer:

| Kolom | Tipe | Keterangan |
|---|---|---|
| `rental_detail_id` | FK → rental_details | Reference rental/unit/project (via assignment→unit & booking→project_location). |
| `report_date` | DATE | Tanggal kerja (≤ hari ini). |
| `start_hm`, `end_hm` | DECIMAL(10,2) | Hour meter awal/akhir (referensi konsistensi durasi). |
| `break_minutes` | INT | Durasi istirahat (menit). |
| `total_work_hours` | DECIMAL(8,2) | Jam kerja aktual (server-side). |
| `standby_hours`, `breakdown_hours` | DECIMAL(8,2) | Detail breakdown/standby. |
| `operator_name`, `notes`, `signature_reference` | String/Text | Metadata operator & tanda tangan PIC. |
| `status` | enum `DRAFT/SUBMITTED/APPROVED/REJECTED` | State machine. |
| `approved_by` | FK → users | Validation metadata (Admin). |

**Unique:** `(rental_detail_id, report_date)` — satu timesheet per unit per hari.

---

## 3. Validasi

1. **Rental harus valid** — `rental_detail` harus eksis dan rental-nya berstatus **`ONGOING`** (selain → `409 BUSINESS_RULE_VIOLATION`).
2. **Date valid** — `report_date` ≤ hari ini.
3. **Duration valid** — `end_hm > start_hm` (`gt` rule).
4. **Working hours konsisten** — `total_work_hours ≥ 0` dan `total_work_hours ≥ breakdown + standby`; break negatif/over 24 jam ditolak.
5. **Duplicate** `(rental_detail, report_date)` → `409 BUSINESS_RULE_VIOLATION`.

---

## 4. Endpoints

| Method | URI | Actor | Deskripsi |
|---|---|---|---|
| `POST` | `/api/v1/timesheets` | `USER`(own), `ADMIN` | Catat timesheet harian (ownership diverifikasi). |
| `GET` | `/api/v1/timesheets` | `USER`(own), `ADMIN`, `OWNER` | List (filter `rental_detail_id`, `status`). |
| `GET` | `/api/v1/timesheets/{timesheet}` | `USER`(own), `ADMIN`, `OWNER` | Detail. |
| `POST` | `/api/v1/timesheets/{timesheet}/submit` | `USER`(own), `ADMIN` | `DRAFT → SUBMITTED`. |

---

## 5. Hasil Pengujian (`TimesheetCoreTest` — 9 kasus)

| Skenario | Hasil |
|---|---|
| Operator membuat timesheet + jam kerja terhitung (break 60 menit → 11h) | **PASSED** |
| Kalkulasi tanpa break → 12h (tanpa pembulatan) | **PASSED** |
| Rental bukan ONGOING → 409 | **PASSED** |
| Validasi durasi (end ≤ start, tanggal masa depan, break negatif) → 422 | **PASSED** |
| Jam kerja tidak konsisten (work < standby+breakdown) → 409 | **PASSED** |
| Duplikat per unit per hari → 409 | **PASSED** |
| Ownership: USER tidak bisa buat timesheet rental orang lain → 409 | **PASSED** |
| USER tidak bisa lihat timesheet asing → 403 | **PASSED** |
| Submit DRAFT→SUBMITTED; double submit → 409 | **PASSED** |

**Total Backend Suite:** 315 tests passed (1153 assertions) • Pint 100% clean.