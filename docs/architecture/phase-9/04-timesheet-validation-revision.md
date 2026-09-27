# Spesifikasi Signature, Validasi & Revisi Timesheet (Phase 9D) - RAFA Rental System

Dokumentasi implementasi tanda tangan PIC (private storage), validasi Admin, penolakan/koreksi, dan riwayat revisi append-only untuk timesheet.

---

## 1. Alur Lengkap

```text
Operator mencatat (DRAFT) ──► submit (SUBMITTED)
        │
        ├── PIC/Operator lampirkan tanda tangan (private storage, Attachment)
        │
        ▼
Admin validasi
   ├── APPROVED ──► (koreksi Admin) ──► revise ──► SUBMITTED (re-validation)
   └── REJECTED (catatan revisi wajib) ──► koreksi
```

---

## 2. Signature (Private Storage)

- **Pengisi:** owner rental (PIC) atau Admin — `TimesheetPolicy::sign`.
- **Upload:** `POST /api/v1/timesheets/{timesheet}/signature` (file `signature`, validasi `FileSecurity` mime/size).
- **Storage:** disimpan pada **disk `local` (private)** — `url` publik tidak pernah diekspos (`AttachmentResource` kini mengembalikan `url: null` untuk berkas privat, foto armada publik tetap menyediakan URL).
- **Reference:** `timesheets.signature_reference` mencatat ID attachment; `document_type = TIMESHEET_SIGNATURE`; audit `TIMESHEET_SIGNED`.
- Hanya untuk status `DRAFT` / `SUBMITTED`.

---

## 3. Validasi Admin

| Aksi | Endpoint | Transisi | Catatan |
|---|---|---|---|
| Approve | `POST /{timesheet}/approve` | `SUBMITTED → APPROVED` | `approved_by` diisi; audit `TIMESHEET_APPROVED`. |
| Reject | `POST /{timesheet}/reject` | `SUBMITTED → REJECTED` | Wajib `reason`; **catatan penolakan dipersist sebagai entri revisi** (`REJECTED: …`); audit `TIMESHEET_REJECTED`. |

Hanya **ADMIN** (admin-exclusive) yang boleh validate/reject/revise (`TimesheetPolicy::validate`).

---

## 4. Koreksi & Riwayat Revisi (BR-017, BR-018)

- **`PUT /{timesheet}/revise`** — Admin koreksi timesheet **APPROVED**:
  1. **Snapshot dulu** nilai lama (`old_start_hm`, `old_end_hm`, `version=count+1`, `revision_reason`, `revised_by`, `created_at`) → baris baru `timesheet_revisions` (append-only; tidak pernah overwrite).
  2. Rekalkulasi `total_work_hours` server-side.
  3. Status kembali `SUBMITTED` (harus divalidasi ulang).
  4. Audit `TIMESHEET_REVISED` (old/new HM, reason, actor).
- **`GET /{timesheet}/revisions`** — histori versi terurut (`revised_by` + nama, timestamp, reason).

**Setiap perubahan menyimpan**: old value, new value, reason, actor, timestamp.

---

## 5. Hasil Pengujian (`TimesheetValidationTest` — 8 kasus)

| Skenario | Hasil |
|---|---|
| Signature di-private-storage + reference tersimpan | **PASSED** |
| MIME invalid (`php`) → 422 | **PASSED** |
| Admin approve SUBMITTED → APPROVED (`approved_by`) | **PASSED** |
| USER tidak bisa approve/reject/revise (403) | **PASSED** |
| Reject dengan revision-note (BR-018) | **PASSED** |
| Koreksi snapshot old values + rekalkulasi hours | **PASSED** |
| Riwayat revisi append-only terurut | **PASSED** |
| Audit (`TIMESHEET_APPROVED`) | **PASSED** |

**Total Backend Suite:** 323 tests passed (1202 assertions) • Pint 100% clean.