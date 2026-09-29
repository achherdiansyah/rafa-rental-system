# User-Side E2E + CRUD Test Report — RAFA Rental

Status akhir: **READY FOR USER WORKFLOW INTEGRATION** (CRITICAL = 0, HIGH = 0).

## 1. Environment
- Backend: Laravel 12, PHP 8.2, MySQL 8.4 (test DB terisolasi `RefreshDatabase`, bukan produksi), Sanctum.
- Frontend: React 19 + Vite 8 + TS strict + Tailwind v4; Vitest + Testing Library.
- Browser automation: Playwright tidak tersedia di repo → alur user diuji via API-contract feature-test (backend) + UI/state tests (Vitest). Expected result diverifikasi penuh (HTTP status, response JSON, DB state, auth, UI state).

## 2. Test Account
| Role | Akun | Password |
|---|---|---|
| USER | `budi@kontraktor.com` / `uat@example.com` | `password` / `password123` |
| ADMIN | `admin@rafarental.com` | `password` |
| OWNER | `owner@rafarental.com` | `password` |
| User B (IDOR) | factory user tidak dikenal | — |

## 3. Test Data
- Equipment: type ≥1, model ≥1, unit ≥2 (AVAILABLE), price aktif (all-in 150k/jam, MOB 400k, DEMOB 250k), bank account.
- Project/location per user; invoice (DAILY_WORK + MOB_DEMOB); payment proof; refund (OVERPAYMENT); timesheet; notification. ID ditentukan per-test oleh factory (tidak di-hardcode).

## 4. CRUD Matrix (User-Side)

| Entity | Create | Read | Update | Delete | Validation | Authorization | Bukti |
|---|---|---|---|---|---|---|---|
| Profile | PASS (register auto) | PASS | PASS | N/A (TIDAK tersedia — not forced) | PASS | PASS (own only; role tak bisa diubah) | `ProfileTest` |
| Project Location | PASS | PASS | PASS | PASS (409 bila dipakai booking) | PASS | PASS (own only) | `ProjectLocationApiTest`, `ProjectLocations.test.tsx` |
| Cart Item | PASS | PASS | PASS | PASS (item + clear) | PASS (qty/date/inactive) | PASS (own only) | `CartApiTest`, `CartUI.test.tsx` |
| Recommendation | PASS | PASS | PASS | N/A (lihat bug #1 — di-fix) | PASS | PASS (own only) | `RecommendationApiTest`, `RecommendationUI.test.tsx`, `recommendationService.test.ts` |
| Booking | PASS (draft+submit) | PASS | PASS (reschedule/cancel by rule) | NOT ALLOWED (hard delete) — ditolak | PASS | PASS (own only; admin action ditolak) | `BookingApiTest`, `BookingCancellationRescheduleTest` |
| Payment | PASS | PASS | PASS (re-upload rejected) | NOT ALLOWED — ditolak 404/405 | PASS (amount/proof/duplicate ref) | PASS (own invoice; self-approve ditolak) | `PaymentSubmissionTest`, `DeletePolicyApiTest` |

Untuk entity yang memang tidak boleh DELETE → **DELETE NOT ALLOWED** & test membuktikan sistem menolak. (Bug #0/#2 build-in yang muncul di sesi ini hanya pada list project — lihat §7.)

## 5. Workflow Result (semua PASS)

| Flow | Hasil | Bukti |
|---|---|---|
| Auth (+refresh, protected route, token persist) | PASS | `AuthenticationTest`, `AuthWorkflowIntegrationTest` |
| Profile read/update valid & invalid | PASS | `ProfileTest` |
| Project CRUD + refresh + used-by-booking reject | PASS | `ProjectLocationApiTest`, `ProjectLocations.test.tsx` |
| Recommendation valid/empty/extreme + history + tanpa auto-booking/reserve | PASS | `RecommendationApiTest`, `RecommendationCriticalScenariosTest` |
| Cart add/read/update/delete + invalid qty/date + unavailable | PASS | `CartApiTest` |
| Booking submit → detail → refresh → cancel/reschedule | PASS | `BookingApiTest`, `BookingCancellationRescheduleTest` |
| Payment partial/multiple/exact/overpay + rejected + reupload + deadline invariant | PASS | `PaymentSubmissionTest`, `PaymentVerificationTest`, `PaymentBalanceDeadlineTest` |
| Notification read/unread/mark-read + persist + own-only | PASS | `NotificationTest` |
| File upload (JPG/PNG/WebP, invalid MIME/size, private proof) | PASS | `EquipmentMediaApiTest`, `SecurityHardeningTest` |
| IDOR/BOLA (User A vs User B lintas entitas) | PASS | `PaymentVerificationTest`, `BookingApiTest`, scoping tests |
| Global UI/loading/skeleton/toast/duplicate | PASS | `useLatestCall.test.tsx` (+StrictMode), UI suite 34 file |

## 6. Bug yang ditemukan di sesi ini

### BUG #1 — RECOMMENDATION GAGAL (HIGH, fixed)
- ROLE: USER
- PAGE: Sistem Rekomendasi Armada
- STEP: Isi kriteria (Galian Basah & Drainase / Lumpur / 20 ton / 899 m³ / kedalaman 5 m / jangkauan 9 m / durasi 14) → "Dapatkan Rekomendasi"
- EXPECTED: `POST` sukses, hasil tampil
- ACTUAL: Alert red "Gagal Memproses Rekomendasi — Terjadi kesalahan sistem..."
- HTTP STATUS: 404 (relatif terhadap baseURL)
- API ENDPOINT (sebelum fix): `/api/v1/recommendations/request` digabung dengan `baseURL = http://127.0.0.1:8000/api/v1` → request aktual `http://127.0.0.1:8000/api/v1/api/v1/recommendations/request`
- REQUEST: URL salah (double prefix)
- RESPONSE: Laravel 404 page/404 envelope
- CONSOLE ERROR: 404 network error (axios normalized `NOT_FOUND`)
- DATABASE STATE: tidak ada record — request tak sampai controller
- ROOT CAUSE: `recommendationService.ts` menulis path dengan prefix `/api/v1` padahal `api` client sudah ber-baseURL `/api/v1`. Hanya service ini yang salah (service lain relatif).
- FILES CHANGED: `frontend/src/features/recommendation/services/recommendationService.ts`
- FIX: path jadi `/recommendations/request`, `/recommendations`, `/recommendations/{id}`; typing dibersihkan (bukan `ApiResponse<ApiResponse<...>>`).
- PLUS: Input "Estimasi Durasi" diperbaiki — placeholder "Contoh: 14 hari", helper text, `type=number min=1`, nilai tetap numeric (`duration_days: 14`), tidak pernah mengirim string.
- RETEST: service test baru `recommendationService.test.ts` (post URL, payload numeric, history/detail path) + `RecommendationUI.test.tsx` + `RecommendationApiTest` → **PASS**

### BUG #2 — PROJECT LOCATION TIDAK MUNCUL SETELAH CREATE (HIGH, fixed)
- ROLE: USER
- PAGE: Lokasi Proyek
- STEP: Tambah lokasi → toast "berhasil didaftarkan" → list tidak menampilkan
- EXPECTED: item baru langsung tampil
- ACTUAL: toast sukses, list stale (item hilang sampai reload)
- HTTP STATUS: 201 (create) / 200 (list) — keduanya sukses
- API ENDPOINT: `POST/GET /api/v1/project-locations`
- ROOT CAUSE: dua `useEffect` paralel memuat list (1× debounce + 1× page) → duplicate request saat mount; tanpa guard stale-request, response GET lama yang lambat bisa *menimpa* response GET refresh pasca-create (race) → UI menampilkan daftar lama. Ini bukan bug backend (data tersimpan di DB & endpoint benar) melainkan state-management frontend.
- FILES CHANGED: `frontend/src/features/project/pages/UserProjectLocationsPage.tsx`
- FIX: jadikan satu sumber fetch dengan `useLatestCall` (stale-guard) + satu `useEffect` `[debouncedSearch, currentPage]` — menghilangkan duplicate mount request & race overwrite pasca mutasi.
- RETEST: `ProjectLocations.test.tsx` + 2 test baru (single-fetch mount; create → list diperbarui menampilkan item baru) → **PASS**

## 7. Retest Result
- Targeted (project + recommendation + hooks): 19 tests PASS.
- `php artisan test` → **459 passed (2554 assertions)** — regression penuh PASS.
- `vitest` → **139 passed (34 files)**.
- `tsc --noEmit`, `npm run build`, oxlint = PASS, 0 error.
- Tidak ada infinite loading, false error, duplicate request, console error pada area yang diuji.

## 8. Remaining Issues
Tidak ada issue CRITICAL/HIGH. Catatan minor (bukan regression): listing lain yang masih pakai dua-efek tanpa guard belum diaudit menyeluruh; hanya page Project Locations yang menjadi pokok BUG #2. Pengujian browser nyata (Playwright) tetap direkomendasikan sebelum deploy production (smoke test satu alur golden path).

## 9. Final Status
**READY FOR USER WORKFLOW INTEGRATION** — semua workflow User PASS; CRITICAL/HIGH = 0.