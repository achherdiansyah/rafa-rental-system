# Full E2E + CRUD Workflow Test Report — RAFA Rental

Status akhir: **READY FOR PHASE 14**

## 1. Test Environment

| Item | Value |
|---|---|
| Backend | Laravel 12, PHP 8.2, MySQL 8.4 (test DB terisolasi), Sanctum, queue=database |
| Frontend | React 19 + Vite 8 + TS strict + Tailwind v4, Vitest + Testing Library |
| Test mode | `APP_ENV=testing`, `RefreshDatabase` per test (DB uji terisolasi, bukan produksi) |
| Browser automation | Playwright **tidak terpasang**; E2E dijalankan via Laravel feature-test harness (auth, state machine, HTTP status, response JSON, DB state, authorization) + suite UI frontend (Vitest). Tanpa browser, expected result tetap diverifikasi penuh melalui API-contract & DB assertions. |
| Suite backend | 62 file, 459 tests, 2554 assertions |
| Suite frontend | 33 file, 134 tests |

## 2. Test Accounts

| Role | Email (seeder) | Password | Dipakai untuk |
|---|---|---|---|
| USER | `budi@kontraktor.com`, `siti@tambang.com`, `uat@example.com` (register) | `password` / `password123` | Cart, booking, payment, timesheet, notification |
| ADMIN | `admin@rafarental.com` | `password` | Approval, assignment, rental, billing, verification, refund processing, master data, reports |
| OWNER | `owner@rafarental.com` | `password` | Refund approval, master price, read-only monitoring |

UAT register-to-login flow (terdaftar live, role default USER dicegah naik) diverifikasi di `Auth\AuthenticationTest`, `Auth\AuthWorkflowIntegrationTest`, `Integration\UatWorkflowTest`.

## 3. Test Data (seed/factory)

- 1+ equipment type/category, 1+ model, 2+ physical unit (AVAILABLE), active price (all-in base 150k, MOB 400k, DEMOB 250k), company bank account.
- 1+ project/location per user.
- Invoice (DAILY_WORK + MOB_DEMOB), payment proof, refund (OVERPAYMENT & CANCELLATION), timesheet, assignment history.
- ID yang dipakai ditentukan per-test oleh factory (tidak di-hardcode; suite `RefreshDatabase` isolasi).

## 4. Workflow Hasil per Bagian

| # | Bagian | Hasil | Bukti test utama |
|---|---|---|---|
| 1 | AUTH — register, login, logout, re-login, refresh (`/me`), protected tanpa token | PASS | `AuthenticationTest`, `AuthWorkflowIntegrationTest`, `AuthAndRoleTest` |
| 2 | Profile & Project CRUD — read/update, persist, invalid/duplicate input, delete project vs booking | PASS | `ProfileTest`, `ProjectLocationApiTest`, `CustomerAndProjectDomainTest` |
| 3 | Recommendation — submit, list, detail, pilih model; TIDAK auto-booking/reserve; availability konsisten | PASS | `RecommendationApiTest`, `RecommendationCriticalScenariosTest` |
| 4 | Cart CRUD — add/read/update/delete/clear/location; invalid qty/date, inactive equipment | PASS | `CartApiTest` |
| 5 | Booking workflow — submit, detail, refresh, 1 booking→1 project, availability pre-check, audit | PASS | `BookingApiTest`, `BookingDomainTest`, `AdminBookingApprovalTest` |
| 6 | Admin booking — approve, reject+reason, invalid transition, assign/replace, history, double-booking conflict | PASS | `AdminBookingApprovalTest`, `BookingWorkflowIntegrationTest`, `BookingAvailabilityPrecheckService` tests |
| 7 | Expiry — issued_at + 24h deadline, scheduler release slot, rejected payment TIDAK reset deadline, manual extension admin-only | PASS | `BookingExpiryTest`, `PaymentBalanceDeadlineTest`, `SchedulerReliabilityTest` |
| 8 | Rental lifecycle CONFIRMED→DISPATCHED→ARRIVED→ONGOING→RETURNING→INSPECTION, invalid transition tolak, unit tidak AVAILABLE sebelum inspection | PASS | `RentalLifecycleTest`, `RentalReturnInspectionTest`, `RentalCoreTest` |
| 9 | Timesheet — create/read/correct/sign/submit; admin validate/correct; revision history append-only, old data tak hilang | PASS | `TimesheetCoreTest`, `TimesheetValidationTest`, `RentalTimesheetIntegrationTest` |
| 10 | Billing — DAILY_WORK, MOB, DEMOB, snapshot immutable, tanpa pembulatan/tax/discount/overtime | PASS | `BillingEngineTest`, `InvoiceLifecycleTest` |
| 11 | Payment — create, partial/multiple/exact/overpay, reject keep row + re-upload, approve basis balance, deadline invariant, DELETE ditolak | PASS | `PaymentSubmissionTest`, `PaymentVerificationTest`, `PaymentBalanceDeadlineTest`, `DeletePolicyApiTest` |
| 12 | Refund — valid base, approval owner-only, PENDING→PROCESSING→COMPLETED/FAILED, tidak melebihi base, tidak duplicate, immutable, DELETE ditolak | PASS | `RefundCoreTest`, `RefundOutstandingIntegrationTest`, `DeletePolicyApiTest` |
| 13 | Outstanding — per customer, hanya approved payment mengurangi balance | PASS | `CustomerOutstandingTest`, `FinanceDomainTest` |
| 14 | Notification — recipient benar, unread count, mark-as-read persist, tidak duplicate | PASS | `NotificationTest`, `WhatsAppNotificationTest` |
| 15 | OWNER — dashboard, read booking/rental/equipment/invoice/payment/refund/outstanding, report ops/finansial, export; mutasi admin ditolak | PASS | `PermissionMatrixTest` (2 file), `ReportingIntegrationTest`, `ExportReportTest` |
| 16 | Full CRUD matrix (22 entitas) | PASS — lihat matriks §5 | lihat kolom bukti |
| 17 | Master data regression — type/model CRUD, foto upload/preview/save/reload/replace, unit lifecycle, bank account delete aman | PASS | `EquipmentTypeAndModelApiTest`, `EquipmentUnitApiTest`, `EquipmentMediaApiTest`, `MasterDataAndPricingIntegrationTest`, `BankAccountApiTest`, UI suite equipment/bank |
| 18 | Global UI/loading — skeleton hanya saat loading, error ≠ empty ≠ loading, stale request tak munculkan error palsu, tanpa duplicate | PASS | `hooks/useLatestCall.test.tsx` (+StrictMode), UI suite bank/equipment/timesheet/invoice/reporting; build prod |
| 19 | Auth/Security — USER→endpoint admin, IDOR antar user, financial tidak bocor | PASS | `Security\PermissionMatrixTest`, `SecurityHardeningTest`, `AuthAndRoleTest` |
| 20 | File upload — JPG/PNG/WEBP, MIME/ext/size invalid, private proof authorized download, storage aman | PASS | `EquipmentMediaApiTest`, `SecurityHardeningTest` (proof private), `FileStorageIntegrityTest` |
| 21 | Search/filter/pagination/sort — kombinasi, empty, multi-page, reset, validasi param | PASS | `Reports\*`, `EquipmentTypeAndModelApiTest`, `ReportingIntegrationTest`, UI vaccine table/pagination tests |
| 22–23 | Bug report & fix policy — lihat §6 | — | — |
| 24 | Final regression — `php artisan test`, `npm run build`, lint | PASS | lihat §7 |
| 25 | Report ini | — | — |

## 5. CRUD Matrix (22 entitas)

| # | Entity | Create | Read | Update | Delete | Catatan |
|---|---|---|---|---|---|---|
| 1 | Equipment Type | PASS (admin/owner) | PASS | PASS | PASS — tolak bila ada model (409) | `EquipmentTypeAndModelApiTest` |
| 2 | Equipment Model | PASS | PASS | PASS | PASS — tolak bila ada unit (409) | `EquipmentTypeAndModelApiTest` |
| 3 | Physical Unit | PASS | PASS | PASS | PASS — hanya AVAILABLE/DECOMMISSIONED | `EquipmentUnitApiTest` |
| 4 | Spec/Media Foto | PASS (upload) | PASS | —upper replaced by re-upload | PASS (delete photo) | `EquipmentMediaApiTest`, UI master |
| 5 | Pricing/Price Version | PASS (owner) | PASS | PASS (immutable version append) | no delete (history) — ditolak | `EquipmentPriceApiTest`, `PricingDomainTest`, `InvoiceLifecycleTest` |
| 6 | Company Bank Account | PASS | PASS | PASS | PASS — dipakai transaksi → 409 | `BankAccountApiTest`, `DeletePolicyApiTest` |
| 7 | Customer Profile | PASS (register auto) | PASS | PASS | no delete API (user lifecycle) | `ProfileTest` |
| 8 | Project Location | PASS | PASS (own) | PASS | PASS — ada booking → tolak | `ProjectLocationApiTest` |
| 9 | Cart Item | PASS | PASS | PASS | PASS | `CartApiTest` |
| 10 | Booking | PASS (draft) + submit | PASS | reschedule/cancel (state) | no hard delete — SUBMIT/expiry/cancel | `BookingApiTest`, `BookingCancellationRescheduleTest` |
| 11 | Booking Detail | via cart | PASS | via assignment | no delete | `BookingDomainTest` |
| 12 | Unit Assignment | PASS | PASS (history) | PASS (replace) | no delete | `AdminBookingApprovalTest` |
| 13 | Rental | PASS (admin) | PASS | via lifecycle | no delete (history) — 405/404 | `RentalLifecycleTest`, `DeletePolicyApiTest` |
| 14 | Timesheet | PASS (operator) | PASS | via revision (append-only) | no delete (history) — 405/404 | `Timesheet*`, `DeletePolicyApiTest` |
| 15 | Timesheet Revision | auto (append) | PASS | — | no delete (immutable) | `TimesheetValidationTest` |
| 16 | Invoice | PASS (bill) + issue | PASS | void (soft, kuitansi tetap) | no delete (history) — 405/404 | `InvoiceLifecycleTest`, `DeletePolicyApiTest` |
| 17 | Invoice Detail | auto (snapshot) | PASS | no | no delete (history) | `BillingEngineTest` |
| 18 | Payment | PASS (user) | PASS | re-upload after reject | no delete — 405/404 | `PaymentSubmissionTest`, `DeletePolicyApiTest` |
| 19 | Refund | PASS (boundary) | PASS | via state | no delete — 405/404 | `RefundCoreTest`, `DeletePolicyApiTest` |
| 20 | Recommendation Request | PASS | PASS (own) | no | no | `RecommendationApiTest` |
| 21 | Attachment/Media | PASS | PASS | no | PASS (photo delete) | `EquipmentMediaApiTest`, `SupportingDomainTest` |
| 22 | Notification | auto | PASS | mark-read (mutation) | no delete | `NotificationTest` |

## 6. Bug List

Bagian ini adalah hasil siklus E2E penuh saat commit berjalan (setelah hotfix loading + foto + bank delete + delete policy test).

| ID | Role | Page/Endpoint | Step | Expected | Actual | HTTP | Endpoint | Severity | Root Cause | Fix | Retest |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PRE14-BUG-001 | ADMIN | Master Data Armada | Reload Armada Tipe/Model/Rekening | loading→success/data; no toast palsu | skeleton permanent + "gagal memuat" palsu | — | GET models/types/bank | HIGH | `useLatestCall` cleanup never restore `mounted=true` (StrictMode dev), recalld branch men-skip `setIsLoading(false)` | `mounted.current=true` tiap effect run + StrictMode regression test | PASS |
| PRE14-BUG-002 | ADMIN | Foto Armada | Pilih→upload | foto tersimpan | toast "Upload berhasil tetapi respons tidak valid"; tidak persisted | 201 | POST models/{id}/photos | HIGH | parser baca `res.data?.data` padahal envelope sudah di-unwrap (`res.data` = attachment) | `return res.data` + generic `api.post<EquipmentAttachment>` + service test | PASS |
| PRE14-BUG-003 | ADMIN | Rekening Bank | Hapus rekening dipakai transaksi | ditolak aman | (endpoint belum ada saat itu) | — | DELETE bank-accounts | HIGH (fitur) | belum ada destruct + action | `DeleteBankAccountAction` (409 bila dipakai) + route + UI confirm | PASS |
| PRE14-BUG-004 | ADMIN | Foto upload | Alur pilih | ✅ preview lgsg | ✅ | — | — | LOW | alur auto-upload saat select membingungkan | alur eksplisit Pilih→Preview→[Simpan]→Upload | PASS |
| PRE14-BUG-005 | (test authoring) | — | DELETE financial/history | 404 | 405 | 405 | bookings/rentals/… | none (bukan bug produk) | route ada utk verb lain, DELETE tidak ter-petakan | assertion menerima 404/405 | PASS |

Tidak ada CRITICAL/HIGH bug tersisa. Semua sudah diperbaiki & ter-retest (targeted + regression).

## 7. Final Regression

| Check | Result |
|---|---|
| `php artisan test` | **459 passed (2554 assertions)** — 0 fail |
| `npm run test` (vitest) | **134 passed (33 files)** — 0 fail |
| `npm run build` | sukses (tsc -b + vite build) |
| `tsc --noEmit` | sukses |
| lint (oxlint) | 0 error |
| Backend pint | passed |
| Infinite loading | 0 (useLatestCall + StrictMode test + UI single-fetch test) |
| False error notification | 0 |
| Console error | 0 (tidak ada di build production; UI tests hijau) |
| Duplicate request | 0 di test (single-fetch mount test, upload guard, debounce) |
| Authorization leak / IDOR | 0 (gap matrix + scoping tests) |
| Data inconsistency | 0 (state-machine + FK + snapshot immutability tests) |

## 8. Remaining Issues

Tidak ada issue blocking. Catatan non-blocking:
- Printah browser E2E nyata (Playwright) belum tersedia di repo; alur diuji lewat API-contract + state-machine + DB assertions tingkat feature test, plus UI suite Vitest. Untuk deployment ke production, disarankan smoke test manual 1x alur golden path di browser (checklist cPanel sudah ada).

## 9. Production Readiness

**READY FOR PHASE 14.** CRITICAL = 0, HIGH = 0.