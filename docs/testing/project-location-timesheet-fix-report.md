# Project Location + Timesheet Fix Report — RAFA Rental

Final: **READY**.

## A. PROJECT LOCATION (frontend runtime/data flow)

### Browser GET endpoint
`GET /api/v1/project-locations?page=1&per_page=9&search=` (axios baseURL `http://127.0.0.1:8000/api/v1`). Response envelope: `{success, message, data:[ProjectLocationResource], meta:{current_page, per_page, total, last_page}}`; resource: `{id, user_id, project_name, address, city, pic_name, pic_phone, latitude, longitude, is_active, created_at, updated_at}`.

### Backend audit (raw repro, payload identik user: Tenggilis / Wahyu Setiawan / 0897789012 / Surabaya / Rungkut Asri)
- POST 201 `{success:true, data:{id:1, is_active:true, …}}`; DB row ada (user_id benar, deleted_at null); GET list `[1]`; GET detail 200; re-login (refresh-equivalent) tetap tampil; A/B isolation PASS. **Backend/DB 100% benar — dicek ulang selama siklus ini.**

### Frontend component / service / state
- `UserProjectLocationsPage.tsx`: satu `useEffect([isAuthenticated, debouncedSearch, currentPage])`, `loadLocations()` dengan `useLatestCall` stale-guard; `create` → verifikasi `res.data.id` → optimistik insert → toast sukses → refresh.
- `projectLocationService.ts`: `createLocation`/`updateLocation` kini `return response` (envelope), konsisten dengan `getLocations` — memperbaiki parsing yang sebelumnya `res.data?.id` selalu `undefined` (root cause false "respons tidak valid").

### Reload / Navigation result
- Record tampil setelah reload (DB & GET terbukti; refresh = request baru, bukan cache).
- Navigasi pergi→kembali: komponen mount ulang → GET lagi → tampil.
- Auth-gate baru: fetch hanya setelah `isAuthenticated` (load ulang tak pernah fetch lebih awal dari context customer).

### CRUD + Ownership result
CREATE PASS · READ PASS (list/detail/search) · UPDATE PASS · DELETE PASS (soft; 409 bila dipakai booking) · OWNERSHIP PASS · tidak ada duplicate request · tanpa hard reload workaround.

## B. TIMESHEET (workflow role)

### Old workflow (diubah)
USER (owner rental) membuat timesheet, submit, lalu sign; Admin hanya validate/reject/revise.

### New workflow (per model operasional)
1. **Admin input** timesheet dari laporan operator (field: unit ONGOING, tanggal, HM mulai/selesai, break, standby, breakdown, operator, catatan) → DRAFT → **submit → SUBMITTED**.
2. **User/PIC** melihat + mendetail + **Konfirmasi & Tanda Tangan**.
3. **Admin** validasi → APPROVED.
4. **Billing DAILY_WORK** hanya memakai timesheet **APPROVED** (tidak berubah; `BillingEngineTest` "only approved are billed" hijau).

### State flow (existing enum, tanpa state baru)
DRAFT → SUBMITTED (Menunggu Konfirmasi User) → (user sign) → APPROVED; REJECTED untuk koreksi (revision append-only).

### Permission (backend policy + API)
- USER: READ+PASS, CONFIRM/SIGN+PASS, CREATE **DENIED (403)**, SUBMIT **DENIED (403)**, VALIDATE **DENIED**.
- ADMIN: CREATE PASS, READ PASS, SUBMIT PASS, VALIDATE (approve/reject/revise) PASS, USER-SIGN **DENIED**.
- OWNER: READ PASS, CREATE/SUBMIT/VALIDATE **DENIED (403)**.
- IDOR: User tak bisa membaca timesheet rental orang lain (403); dan tak bisa melihat/ubah/tandatangani punya User B.

### Frontend
- `UserTimesheetsPage` → **READ + CONFIRM + SIGN ONLY** (list + detail modal + riwayat revisi; "Catat Timesheet"/form/Simpan dihapus; submit tombol dihapus).
- `AdminTimesheetsPage` → tambah **"Input Timesheet"** (modal: unit ONGOING + field operator report; Simpan = create + submit → SUBMITTED) di samping validasi/koreksi/riwayat.

## TEST RESULT
Project Location: CREATE PASS · READ PASS · UPDATE PASS · DELETE PASS · RELOAD PASS · NAVIGATION PASS · OWNERSHIP PASS
Timesheet: USER READ PASS · USER CONFIRM/SIGN PASS · USER CREATE DENIED(403) · USER EDIT DENIED · ADMIN CREATE PASS · ADMIN EDIT PASS · ADMIN VALIDATE PASS · OWNER READ PASS · OWNER MUTATION DENIED(403)
Regression: Backend **467 passed (2603 assertions)** · Frontend **144 passed (37 files)** · Build PASS · Lint/tsc PASS

## Files Changed
- `backend/app/Policies/TimesheetPolicy.php` — create & submit = ADMIN-only.
- `backend/tests/.../TimesheetCoreTest.php`, `TimesheetValidationTest.php`, `RentalTimesheetIntegrationTest.php`, `Billing/Billing*`, `Invoice/InvoiceLifecycleTest.php`, `Payment/Payment*`, `Integration/{UatWorkflowTest,CoreFlowRegressionTest}.php` — flow actor di-update ke admin-input.
- `frontend/src/features/timesheet/pages/UserTimesheetsPage.tsx` — read+confirm+sign (form dihapus).
- `frontend/src/features/timesheet/pages/AdminTimesheetsPage.tsx` — Input Timesheet modal.
- `frontend/src/features/timesheet/UserTimesheetsPage.test.tsx` — suite baru.
- `frontend/src/features/project/pages/UserProjectLocationsPage.tsx` — auth-gate fetch.
- `frontend/src/features/project/ProjectLocations.test.tsx` — useAuth mock.
- `docs/testing/project-location-timesheet-fix-report.md`.

## Final Status
**READY.** CRITICAL/HIGH = 0. Tidak ada business rule baru yang dibuat; state machine & billing basis (APPROVED-only) dijaga.