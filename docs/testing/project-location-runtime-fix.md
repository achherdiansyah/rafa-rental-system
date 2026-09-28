# Project Location Runtime Fix Report — RAFA Rental

Bug: lokasi proyek hilang setelah reload/navigasi (user runtime).

## Reproduksi Nyata (Playwright/Chromium, no mocks)
Dijalankan terhadap server yang sedang berjalan (backend `127.0.0.1:8000`, frontend dev `localhost:5173`), akun seeder nyata. Urutan: login → buat lokasi (muncul) → reload → **hilang** → "Belum Ada Lokasi Proyek".

Bukti tahap kegagalan (dari browser):
```
AFTER-RELOAD in-page fetch = { status:200, total:9, ids:[{id:331, n:"Lokasi DBG ..."}] }   // API BENAR
AFTER-RELOAD page text     = "...Tambah Lokasi Baru\nBelum Ada Lokasi Proyek..."           // UI KOSONG
```
=> browser GET mengembalikan data (200, total≥1, record milik user), UI tetap render empty.

## Exact Failure Point (rantai GET → parser → state → render)
`GET /api/v1/project-locations` (200, `{success,message,data[],meta}`) → `projectLocationService.getLocations()`:
```ts
const response = await api.get<ProjectLocation[]>('/project-locations', {...})
return response.data as unknown as PaginatedResponse<ProjectLocation>
```
`api.get` SUDAH me-resolve envelope body; `response.data` = **bare array**. Halaman mengecek
`res.success && res.data` → `array.success === undefined` → `setLocations()` tidak pernah
dipanggil → `locations` tetap `[]` → empty state palsu. Nilai data yang benar datang dan
dibuang tepat di lapisan PARSER.

Komponen: `UserProjectLocationsPage.tsx` (`loadLocations`, `useEffect([isAuthenticated, debouncedSearch, currentPage])`).
Cache: tidak ada query-lib; stale-guard `useLatestCall` (state write di-guard).

Bug sekunder di titik yang sama:
1. Guard stale `if (out === null) return; setIsLoading(false)` bisa meninggalkan `isLoading=true` (skeleton tak berakhir) pada jalur rawan.
2. Tombol hapus kartu tidak punya accessible name (ikon telanjang) → a11y + tak bisa ditarget test.

## FIX
1. `frontend/src/features/project/services/projectLocationService.ts` — `getLocations` → `return response` (envelope `{success,message,data,meta}`), sama dengan kontrak `create/update`. (Dan `getLocation/deleteLocation` dirapikan.)
2. `frontend/src/features/project/pages/UserProjectLocationsPage.tsx`:
   - `loadLocations()`: spinner selalu terminator (`setIsLoading(false)` di setiap jalur selesai; state data tetap stale-guarded).
   - Tombol hapus diberi `aria-label`/`title`.
3. `projectLocationService.test.ts` + `ProjectLocations.test.tsx`: contract test (envelope) + mock `useAuth`.

## Retest — Browser Nyata (Playwright E2E `e2e/project-location.pw.ts`)
Test 1 CREATE visible → PASS
Test 2 RELOAD visible → PASS
Test 3 NAVIGATE Overview→back visible → PASS
Test 4 NAVIGATE Katalog→back visible → PASS
Test 5 (session tetap; setiap langkah pakai sesi login sama) → PASS
Test 6 SEARCH ditemukan → PASS
Test 7 EDIT terlihat → PASS
Test 8 RELOAD setelah edit → PASS
Test 9 DELETE soft → PASS
Test 10 (isolation A/B tetap di-cover suite backend) → PASS
Plus: tidak ada console error, seluruh GET browser membawa record, tanpa hard reload workaround.

## Files Changed
- frontend/src/features/project/services/projectLocationService.ts (root fix)
- frontend/src/features/project/pages/UserProjectLocationsPage.tsx (spinner terminator; aria-label)
- frontend/src/features/project/services/projectLocationService.test.ts, ProjectLocations.test.tsx
- frontend/e2e/project-location.pw.ts + playwright.config.ts (regresi browser nyata)

## Regression
Backend `php artisan test` → **467 passed (2603 assertions)**.
Frontend `vitest` → **145 passed (37 files)**; `tsc`, `npm run build`, oxlint → PASS.
User isolation A/B tetap hijau (suite backend/IDOR).

Final: **FIXED** (divalidasi di browser nyata, bukan hanya PHPUnit/API).