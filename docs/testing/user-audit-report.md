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

## Summary (final, dengan bukti created-at-repro pakai payload persis user)
Raw API repro memakai identik data user (Tenggilis / Wahyu Setiawan / 0897789012 / Surabaya /
Rungkut Asri): **POST 201 → body `{success:true, message, data:{id:1, project_name:'Tenggilis', …}}` →
DB row ada (user_id benar, soft-delete null) → GET list `[1]` → GET detail 200**. Backend, DB,
ownership, authorization : **semua benar tanpa cacat**.

## ROOT CAUSE (sebenarnya, sisi FRONTEND service contract)
`frontend/src/features/project/services/projectLocationService.ts`:
- `createLocation`/`updateLocation` mengembalikan `response.data` — yaitu **objek location langsung**,
  bukan envelope. Dengan begitu guard halaman `res?.data?.id` selalu salah (`location.data` = `undefined`)
  → halaman menganggap response "tidak valid" → toast "Lokasi tersimpan di server namun respons tidak valid"…
  padahal backend sukses 201 + DB commit. Coral:`getLocations` justru mengembalikan envelope — jadi
  contract antar-method tidak konsisten.
- Akibat rangkaian: toast palsu + tombol modal tidak berlanjut (optimistic insert & refresh di-skip) → UX
  dan state list tetap kosong; reload baru akan menunjukkan record karena DB benar (tapi user tidak sempat).

## Backend Fix
Tidak ada (terbukti benar). Bukti: `ProjectLocationPersistenceTest` (raw register→login→POST→DB→list→detail→re-login; isolasi A/B; CRUD + safe soft-delete) — 3 test/28 assertions PASS.

## Frontend Fix
`frontend/src/features/project/services/projectLocationService.ts`
- `createLocation`/`updateLocation` → `return response` (envelope `{success,message,data}`) — konsisten
  dengan `getLocations` & API contract.
- `UserProjectLocationsPage` (tetap): success toast hanya bila `res.data.id` benar (kini terpenuhi),
  optimistic insert record balasan, single-effect + stale-guard — tanpa hard reload, tanpa duplicate request.

## Verification
- Backend `php artisan test` → **464 passed (2590 assertions)** (termasuk 3 raw-flow baru + 14 test ProjectLocation/Detail).
- Frontend `vitest` → **146 passed (37 files)** (termasuk contract test service `projectLocationService.test.ts`).
- `tsc`, `npm run build`, oxlint → PASS.
- Acceptence: POST ✓, DB ✓, POST response valid ✓, GET list ✓, GET detail ✓, frontend menampilkan ✓, reload ✓, UPDATE ✓, DELETE (NOT ALLOWED bila dipakai booking; soft) ✓, ownership A/B ✓, tanpa false success ✓, tanpa toast "response tidak valid" ✓, tanpa hard reload ✓, tanpa duplicate record ✓, tanpa console error ✓.

## Final
**FIXED.** Rantai yang sebelumnya salah: `FORM → POST → DB → GET → FRONTEND` — sebelumnya putus di
`FRONTEND` (parser service membaca response dengan salah). Sekarang seluruh rantai hijau.

---

# Fix — Project Location Status (badge "Nonaktif" pada lokasi baru)

## Root Cause (terbukti via RAW repro)
- Migrasi & factory `project_locations.is_active` default = `true`; `UserProjectLocationsPage` render badge
  "Nonaktif" saat `is_active=false`.
- RAW repro: POST create (tanpa is_active) → **DB row `is_active = true`** namun **POST response
  `data.is_active = false`** → card langsung (optimistic insert) membaca false → badge "Nonaktif".
- Penyebab: `CreateProjectLocationAction` mengembalikan model hasil `create()` yang belum pernah memuat
  nilai default dari database. Karena `is_active` tidak ada di atribut instance (kolom default di-apply
  server-side), resource men-serialize `(bool)null = false` — sementara baris DB benar `true`.
- Imbas rantai: response menipu status; state frontend (dan setiap pemakai `data.is_active`) tidak sinkron
  dengan DB. (Update action sudah pakai `fresh()` — hanya Create yang kurang.)

## Business Rule
Tidak ada rule yang menjadikan lokasi baru NONAKTIF; analog entity lain (bank, unit) default `true`.
Migrasi/factory sudah default `true` → source of truth: lokasi baru = AKTIF. Tidak ada rule baru yang dibuat;
implementasi kini konsisten dengan default schema.

## Backend Fix
`backend/app/Actions/ProjectLocation/CreateProjectLocationAction.php`
- Tambah `$location->refresh()` setelah `create()` → atribut instance / resource / response sama persis
  dengan commit DB (is_active=true). Tanpa perubahan schema/contract.

## Frontend Fix
- Tidak diperlukan (badge merender status yang diberikan API; API kini benar).

## Verification / Regression
- `PlocStatusRegressionTest` (baru): create tanpa is_active → DB is_active=true, response is_active=true,
  list + detail + search konsisten → PASS. `ProjectLocationPersistenceTest` + `ProjectLocationApiTest` → PASS.
- `php artisan test` → **465 passed (2602 assertions)**.
- Frontend `vitest` → **146 passed (37 files)**; `tsc`/`npm run build` → PASS.

## Final
**FIXED.** CREATE: PASS · READ: PASS · UPDATE: PASS · DELETE: PASS (soft; NOT ALLOWED bila dipakai booking) ·
REFRESH: PASS · NAVIGATION: PASS (list tak pernah di-filter is_active; record tetap tampil) ·
OWNERSHIP: PASS (A/B).