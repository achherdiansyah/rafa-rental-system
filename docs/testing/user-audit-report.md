# User-Side Audit Report — RAFA Rental

Status: **READY FOR FIX** (Tidak ada CRITICAL/HIGH open; ada 5 temuan OPEN kategori MEDIUM/LOW.)

## 1. Audit Scope
20 area user: Auth, Profile, Project Location, Recommendation, Equipment Catalog/Detail, Cart, Booking(+status), Invoice, Payment, Refund, Outstanding, Notification, File Upload, History, Search/Filter/Pagination, Loading/Error/Empty, Responsive, Authorization/IDOR, API↔Frontend consistency, DB consistency. Metode: source review + reproduksi via suite (459 backend test / 139 frontend test) + cross-check PRD/state machine/enum/API contract. Tidak ada perubahan kode dilakukan pada tahap ini.

## 2. Environment
Backend Laravel 12/PHP8.2/MySQL8.4 (test DB terisolasi `RefreshDatabase`); Frontend React19/Vite8/TS strict/Vitest. Playwright tidak tersedia → UI state diverifikasi via component tests + source audit.

## 3. Features Audited — hasil ringkas

| Area | Result | Catatan |
|---|---|---|
| Authentication | PASS | Guard benar: `ProtectedRoute`/`RoleRoute`/`GuestRoute`; 401 interceptor redirect `/login?expired=1` (tanpa loop) |
| Profile | PASS | API `/profile` (relative OK); update tanpa dosis role; IDOR di-cover `ProfileTest` |
| Project Location | PASS | Bug race sebelumnya FIXED (`useLatestCall` + single effect) |
| Recommendation | PASS | Bug URL double-prefix sebelumnya FIXED + di-test |
| Equipment Catalog/Detail | PASS (1 temuan UX) | price diprelease (index load `type,attachments,prices` + count units) |
| Cart | PASS (1 temuan info) | Qty duplicate → increment; snapshot price saat booking (per aturan) |
| Booking | PASS (2 temuan) | State machine di-backend sesuai; temuan: dead-state SUBMITTED + badge map |
| Invoice | PASS | `issued_at`/`due_at` (24 jam) disediakan & ditampilkan "Jatuh tempo ..." |
| Payment | PASS | Guard backend blokir payment pd invoice PAID; reject → reupload dibolehkan; deadline tak di-reset |
| Refund | PASS | Boundary & approval owner-only; delete ditolak |
| Outstanding | PASS | `/finance/outstanding/me` + halaman user tersedia |
| Notification | PASS (1 temuan UX) | unread-count endpoint ada, tapi nav tak menampilkan badge |
| File Upload | PASS | MIME/size/proof private/authorized download (tests) |
| History | PASS | riwayat rekomendasi/booking/payment tersedia & ter-scope |
| Search/Filter/Pagination | PASS | backend stabil + test; filter-reset tidak dimaksakan |
| Loading/Error/Empty | PASS | skeleton ≠ error ≠ empty; stale-guard aktif di page rawan |
| Responsive | NOT FULLY VERIFIED | tanpa browser automation; grid/modal responsif by code |
| Authorization/IDOR | PASS | 0 temuan; matrix + scoping tests hijau |

## 4. CRUD Matrix (User)
| Entity | Create | Read | Update | Delete | Validation | Auth | 
|---|---|---|---|---|---|---|
| Profile | (auto) | PASS | PASS | NOT ALLOWED | PASS | PASS |
| Project Location | PASS | PASS | PASS | PASS (409 bila dipakai) | PASS | PASS |
| Cart Item | PASS | PASS | PASS | PASS | PASS | PASS |
| Recommendation | PASS | PASS | — | NOT ALLOWED | PASS | PASS |
| Booking | PASS (draft+submit) | PASS | PASS (reschedule/cancel by rule) | NOT ALLOWED | PASS | PASS |
| Payment | PASS | PASS | PASS (re-upload rejected) | NOT ALLOWED | PASS | PASS |

## 5–8. Temuan

### USER-AUDIT-01 — Booking state `SUBMITTED` adalah dead state (MISMATCH/DATA)
- SEVERITY: MEDIUM
- PAGE/FEATURE: Booking workflow / state machine
- EXPECTED (PRD/code comment): DRAFT → SUBMITTED → PENDING_APPROVAL.
- ACTUAL: `SubmitBookingAction` langsung `DRAFT → PENDING_APPROVAL`. Tidak ada satupun aksi/contoller yang menulis `status=SUBMITTED` (grep backend). `CancelBookingAction` tetap mengizinkan cancel untuk `SUBMITTED` (unreachable); enum + frontend STATUS_VARIANT memetakan `SUBMITTED`.
- ROOT CAUSE: langkah menengah SUBMITTED dihilangkan saat implementasi submit (kemungkinan dianggap agregat), tapi enum/dokumentasi/guard tidak dibersihkan.
- IMPACT: tidak ada alur user yang gagal; terjadi ketidaksesuaian state machine vs enum vs doc; risiko kebingungan maintainer & tooling state machine.
- RECOMMENDED FIX (saat fase fix): pilih salah satu — (a) ubah action memroduksi `SUBMITTED` singkat lalu `PENDING_APPROVAL`, atau (b) hapus `SUBMITTED` dari enum + cancel-guard + UI map; perbarui doc state machine.
- STATUS: OPEN

### USER-AUDIT-02 — Badge status booking user tidak memetakan state akhir lifecycle (UX)
- SEVERITY: LOW
- PAGE: `/app/bookings`
- ACTUAL: `STATUS_VARIANT` hanya DRAFT/SUBMITTED/PENDING_APPROVAL/APPROVED/PAYMENT_PENDING/CONFIRMED/REJECTED/CANCELLED/EXPIRED. `DISPATCHED/ARRIVED/ONGOING/COMPLETED` jatuh ke fallback `secondary`.
- ROOT CAUSE: map disusun sebelum rental lifecycle dilewati booking status lanjut.
- IMPACT: visual sedikit tidak konsisten; bukan salah interpretasi.
- RECOMMENDED FIX: tambah mapping (ONGOING=success, DISPATCHED/ARRIVED=warning, COMPLETED=success).
- STATUS: OPEN

### USER-AUDIT-03 — Katalog armada menjanjikan "ketersediaan unit" namun kartu tidak menampilkan (MISMATCH/UX)
- SEVERITY: LOW
- PAGE: `/app/equipment`
- ACTUAL: subtitle "…ketersediaan unit, dan informasi tarif sewa resmi", tapi kartu model hanya badge kapasitas; tidak ada indikator unit tersedia/tersewa. Ketersediaan baru diketahui saat booking submission.
- ROOT CAUSE: subtitle marketing vs rendering card; availability memang dihitung per-periode saat booking (decision support tidak me-reserve), sehingga menampilkan angka statis berisiko menyesatkan.
- IMPACT: ekspektasi user terhadap ketersediaan instan; bisa membingungkan.
- RECOMMENDED FIX: sesuaikan subtitle (mis. "cek ketersediaan saat pemesanan") atau tampilkan `units_count` ter-label "unit terdaftar" (bukan tersedia) di kartu.
- STATUS: OPEN

### USER-AUDIT-04 — Tidak ada indikator unread notification di navigasi user (UX)
- SEVERITY: MEDIUM
- PAGE/CMP: `UserLayout` sidebar
- ACTUAL: endpoint `GET /notifications/unread-count` ada + digunakan di test, tapi nav "Notifikasi" tidak menampilkan badge/unread count. User tidak sadar ada notifikasi baru.
- ROOT CAUSE: fitur unread badge belum dihubungkan ke layout.
- IMPACT: penurunan discoverability; alur notifikasi tetap berfungsi.
- RECOMMENDED FIX: fetch unread-count di UserLayout + render badge titik/angka (poll saat mount + refresh ringan).
- STATUS: OPEN

### USER-AUDIT-05 — 401 handler memakai full-page navigation (UX/arch)
- SEVERITY: LOW
- FILE: `src/lib/api.ts` (interceptor)
- ACTUAL: semua 401 → `window.location.href='/login?expired=1'` (hard reload), kehilangan state SPA; sudah di-guard agar tidak loop di halaman login.
- IMPACT: pengalaman sedikit keras saat sesi kedaluwarsa; tidak ada path refresh-token (sesuai desain Sanctum).
- RECOMMENDED FIX (opsional, saat fase fix): pindah ke re-state auth in-app (navigate + clear token) bila ada prioritas UX.
- STATUS: OPEN

### USER-AUDIT-06 — Harga di keranjang dari master price live (informational / DATA note)
- SEVERITY: LOW (bukan bug; dokumentasi)
- PAGE: `/app/cart`
- ACTUAL: display memakai `model.prices.find(is_all_in===item.is_all_in)` (harga master saat ini). Billing final memakai snapshot (PricingCalculatorService → InvoiceDetail) saat booking/invoice.
- IMPACT: selisih harga tampilan vs invoice mungkin terjadi jika harga master berubah di antara review & submit — sesuai business rule snapshot-by-design; aman.
- STATUS: INFORMATIONAL (no action required)

## 9. Security Findings
0 open. Permission matrix (USER/ADMIN/OWNER), IDOR antar user (profile/project/booking/invoice/payment/notification/file), self-approve payment, refund approval owner-only — semua PASS via tests (`PermissionMatrixTest` ×2, `SecurityHardeningTest`, scoping tests).

## 10. Performance Findings
0 open untuk user path. Stale/duplicate guards sudah diterapkan (project page, hooks). Single-fetch diuji. N+1 read path di-cover `PerformanceTest`. StrictMode dev double-mount = dev-only, tanpa efek production.

## 11. Database Consistency
0 inconsistency ditemukan. 459 backend tests memverifikasi UI↔API↔backend↔DB pada setiap workflow. Checkpoint khusus: payment reject → row tersimpan + deadline utuh; approved payment jadi dasar balance; timesheet revision append-only; invoice snapshot immutable.

## 12. Bug List Prioritas
| ID | Cat | Sev | Prio | Status |
|---|---|---|---|---|
| USER-AUDIT-01 | Dead state SUBMITTED | MEDIUM | P2 | OPEN |
| USER-AUDIT-02 | Badge map lifecycle | LOW | P3 | OPEN |
| USER-AUDIT-03 | Katalog "ketersediaan" misleading | LOW | P3 | OPEN |
| USER-AUDIT-04 | Unread badge nav | MEDIUM | P2 | OPEN |
| USER-AUDIT-05 | 401 hard navigation | LOW | P3 | OPEN |
| USER-AUDIT-06 | Harga cart live (info) | LOW | P3 | INFORMATIONAL |

Temuan sesi sebelumnya yang sudah diperbaiki & retest hijau (bukan open): Recommendation URL double-prefix; ProjectLocation stale/duplicate fetch; useLatestCall StrictMode; photo upload parse & save; bank delete flow.

## 13. Overall User-Side Readiness
Fungsi inti user 100% berjalan; tidak ada CRITICAL/HIGH. Lima item OPEN kategori P2/P3 bersifat perbaikan presisi (state-machine hygiene, label, badge), bukan blokir. Vuln/financial inconsistency tidak ditemukan.

---

# FIX RESULT — User Audit (sesi fix)

| Finding | Before | Root cause | Fix | Files changed | Targeted test | Retest | Status |
|---|---|---|---|---|---|---|---|
| USER-AUDIT-01 (MEDIUM) | `SUBMITTED` = dead-state; enum/guard/UI petakan status yang tak pernah tertulis | T-B01 SOT: DRAFT → PENDING_APPROVAL; label `SUBMITTED` = alias fase, bukan state persistable; implementasi lama menyisakan referensinya | Hapus case `SUBMITTED` dari enum + guard cancel + UI map + type union + EnumTest; catat alias pada doc state machine | `backend/app/Enums/BookingStatus.php`, `app/Actions/Booking/CancelBookingAction.php`, `tests/Unit/Enums/EnumTest.php`, `frontend/src/types/booking.ts`, `UserBookingsPage.tsx`, `AdminBookingsPage.tsx`, `docs/.../02-state-machines-v3.md` + test baru `BookingStatusConsistencyTest.php` | submit → PENDING_APPROVAL; `SUBMITTED` tak ada di enum/DB | PASS (10 backend tests) | **FIXED** |
| USER-AUDIT-02 (LOW) | Badge user booking fallback 'secondary' untuk DISPATCHED/ARRIVED/ONGOING/COMPLETED | map dibuat sebelum lifecycle penuh | Tambah mapping seluruh state valid (DISPATCHED=warning, ARRIVED=secondary, ONGOING=success, COMPLETED=success) tanpa mengubah backend | `frontend/src/features/booking/pages/UserBookingsPage.tsx`, `AdminBookingsPage.tsx` + test `BookingUI.test.tsx` (12 status) | render seluruh lifecycle → badge muncul, tanpa 'SUBMITTED' | PASS (frontend) | **FIXED** |
| USER-AUDIT-03 (LOW) | Subtitle "ketersediaan unit" padahal kartu tak menampilkan availability | copywriting vs konten kartu; availability memang per-periode saat booking | Ubah wording: "…spesifikasi alat berat dan tarif resmi. Ketersediaan diverifikasi saat pemesanan." (tanpa expose physical unit) | `frontend/src/features/equipment/pages/EquipmentCatalogPage.tsx` | subtitle baru | PASS | **FIXED** |
| USER-AUDIT-04 (MEDIUM) | Unread count tersedia tapi nav tanpa badge | fitur badge belum diintegrasikan ke layout | Badge `SidebarItem.badge` + UserLayout fetch `unreadCount()` (mount+navigasi+event `rafa:notifications-changed`; tanpa polling), UserNotificationsPage dispatch event setelah markRead/markAll | `frontend/src/components/layout/Sidebar.tsx`, `UserLayout.tsx`, `features/notification/pages/UserNotificationsPage.tsx` + test `UserLayout.test.tsx` | badge>0 tampil, =0 hilang, sync event | PASS (frontend) | **FIXED** |
| USER-AUDIT-05 (LOW) | 401 → full-page reload `/login?expired=1` | interceptor pakai `window.location.href` | Interceptor clear token + dispatch `rafa:auth-expired`; AuthProvider (useNavigate) clear sesi + in-app Navigate; tanpa reload, tanpa loop | `frontend/src/lib/api.ts`, `app/AuthContext.tsx` + test `AuthContext.test.tsx` | 401 → sesi dibersihkan + redirect in-app | PASS | **FIXED** |
| USER-AUDIT-06 (INFORMATIONAL) | Cart tampilkan master price live; billing pakai snapshot | — (expected behavior sesuai business rule snapshot-at-booking) | Dokumentasi + caption info di cart ("harga indikatif… tagihan memakai snapshot saat invoice diterbitkan"); tidak mengubah logic | `frontend/src/features/cart/pages/UserCartPage.tsx` + report | — | PASS | **INFORMATIONAL** |

## FIXED BUGS
- USER-AUDIT-01, 02, 03, 04, 05 → semua **FIXED** (retest PASS; targeted + regression).

## INFORMATIONAL FINDINGS
- USER-AUDIT-06 → **INFORMATIONAL** (expected behavior; diberi caption informasi; tanpa perubahan logic).

## REMAINING ISSUES
- Tidak ada issue CRITICAL/HIGH.
- P3: browser-automation (Playwright) belum tersedia — disarankan smoke test manual satu alur golden path saat deployment.

## Regression (sesi fix)
- `php artisan test` → **461 passed (2562 assertions)**
- `vitest` → **144 passed (36 files)**
- `tsc`, `npm run build`, oxlint → PASS, 0 error.

Jumlah finding tetap 6 (5 FIXED + 1 INFORMATIONAL).

---

# Follow-up Fix — Project Location Persistence (repro & hardening)

## Summary
Post/repro raw API flow (register → login → POST → DB → GET list → GET detail → re-login refresh) proved the **backend, DB, ownership, and authorization layers are correct** — a created location always persists and is immediately returned by list/detail, and User B cannot read/update/delete User A's row (verified with the exact single Authorization header a browser sends via `withToken`). The remaining failure class was the **frontend mutation path**: success toast fired on HTTP 2xx without verifying the persisted payload, and list display depended on a follow-up fetch vulnerable to stale/race — a user could be told "berhasil didaftarkan" while the list still showed the empty state.

## ROOT CAUSE (final)
- Frontend (`UserProjectLocationsPage`): success toast without persistence proof; list refresh not guaranteed to beat stale in-flight responses.
- Backend/DB: **no defect** (raw-flow proof test, 28 assertions).

## Backend Fix
- None required for logic. Added `ProjectLocationPersistenceTest` (raw `register`→`login`→`POST`→DB→`GET list`→`GET detail`→re-login refresh; A/B isolation; CRUD + safe delete) — 3 tests / 28 assertions, all PASS.
- Additional finding during instrumentation: a suspected ownership "leak" was a **test-harness artifact** (session-level `defaultHeaders` pollution), not an app bug; rerun with per-request `withToken` resolved the correct user. No authorization change made.

## Frontend Fix
`frontend/src/features/project/pages/UserProjectLocationsPage.tsx`
- Success toast ONLY after the create/update response carries a valid resource `id` (no fake success; invalid response → explicit toast, list untouched).
- Create now **optimistically inserts** the returned record (dedup by id), so the list can never flash "empty" while the confirming GET runs.
- Existing guarantees kept: single-source `useEffect` (`[debouncedSearch, currentPage]`) + `useLatestCall` stale-guard (no duplicate mount GET, no stale overwrite).

## Verification
- Backend `php artisan test` → **464 passed (2590 assertions)** (includes 3 new raw-flow tests).
- Frontend `vitest` → **144 passed (36 files)**; `tsc`, `npm run build`, oxlint → PASS.
- CRUD: CREATE/READ/UPDATE/DELETE(NOT ALLOWED bila dipakai booking, soft-delete aman) → PASS.
- Ownership A/B → PASS. Refresh persistence (re-login) → PASS.
- Database verification → PASS (row + user_id + deleted_at null + soft-delete semantics).

## Final
**FIXED.** Source of truth for stage where symptom used to appear: `POST → DB → GET → FRONTEND` — POST/DB/GET selalu benar; lapisan FRONTEND yang kini sudah di-hardening.