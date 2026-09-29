# Owner + Admin CMS Audit/Fix Report — RAFA Rental

Final: **READY**.

## 1. Owner — Executive Summary (dulu placeholder, sekarang KPI dashboard)
- Masalah: `/owner` & `/owner/revenue` sebelumnya `OwnerPortalPlaceholder` (kosong).
- Fix: `OwnerExecutiveSummaryPage` — KPI cards (8) dari endpoint agregat read-only `GET /api/v1/reports/dashboard` (sudah di-scope ADMIN/OWNER): Pembayaran Disetujui, Outstanding/Piutang, Booking, Rental Aktif, Jam Kerja Timesheet, Utilisasi Armada, Invoice, Refund; plus filter periode (`from`/`to`). Tanpa tabel transaksi di halaman utama. Tanpa mock/placeholder.

## 2. Owner — Laporan Pendapatan (bedakan dengan Executive)
- Fix: `OwnerRevenueReportPage` — report DETAIL via `ReportExplorer` reusable (tabs Invoice/Pembayaran/Sebagian/Outstanding/Kelebihan Bayar/Refund; search/filter periode+customer+status, sort, pagination, detail, export CSV) memakai `GET /api/v1/reports/financial/*`. Berbasis pembayaran disetujui (aturan existing, tidak diubah).
- Perbedaan fungsi: Exec = cards/KPI; Revenue = tabel+filter+export. Komponen bersama sebatas ReportExplorer; kedua halaman tidak identik.

## 3. Owner — Permission
- Read/monitoring: PASS (OwnerReportingAccessTest — dashboard + 6 finansial endpoint 200).
- Mutation tetap dilarang (booking assign/validasi/approve payment dst tetap di gate existing; CMS di-blok untuk Owner, lihat §8).

## 4–8. Admin — CMS Landing Page
- Audit schema: tidak ada tabel CMS existing → tambah **satu tabel** `cms_settings` (key unique, value, is_active, updated_by) + reuse **`attachments`** polymorphic (document_type `CMS_MEDIA`, disk `public`, `FileSecurity` untuk MIME/ukuran). Tanpa duplicate model.
- Backend `CmsController`:
  - Public (guest): `GET /api/v1/cms/public` — settings aktif, media → URL `/storage/...`.
  - Admin-only (`role:ADMIN`): `GET/PUT/DELETE /api/v1/admin/cms/{key}` + `POST /{key}/media` (upload/replace, throttle:file-upload, validasi jpg/png/webp ≤5MB via `FileSecurity`).
- Frontend `AdminCmsPage` + route `/admin/cms` + nav "CMS Landing Page":
  - Brand: nama + logo/favicon (upload+preview+ganti+hapus).
  - Navbar: menu JSON.
  - Hero: judul, subjudul, CTA teks/link, gambar hero (upload/replace).
  - Section Content: about/services/cta/footer.
  - Simpan Konten → PUT per key; media di-upload langsung dengan preview → landing langsung membaca perubahan (tanpa reload manual).
- Landing `HomePage` membaca `GET /cms/public` → hero title/subtitle/CTA/hero image & brand dari CMS (fallback default bila kosong). HomePage.test (`Admin update → DB → public API → landing`) PASS.

## 9. Owner — UI Review
Executive Summary ≠ Laporan Pendapatan terbukti (fungsi beda; komponen report dipakai hanya untuk Revenue).

## Test Result
- Backend: **473 passed (2636 assertions)** (+`CmsCrudApiTest` 4, `OwnerReportingAccessTest` 2 — authorization CMS admin-only, owner read, media validation/replace/disable/public-sync).
- Frontend: **151 passed (39 files)** (+`cmsService.test`, `HomePage.test`; RouteGuard/AuthFlow di-update ke judul 'Executive Summary').
- Build/tsc/lint: PASS.

## Files Changed
- Backend: `database/migrations/2026_09_28_000001_create_cms_settings_table.php`, `app/Models/CmsSetting.php`, `app/Http/Controllers/Api/V1/CmsController.php`, `routes/api.php`, tests `CmsCrudApiTest`, `OwnerReportingAccessTest`.
- Frontend: `features/cms/services/cmsService.ts`(+test), `features/cms/pages/AdminCmsPage.tsx`, `features/reporting/pages/OwnerExecutiveSummaryPage.tsx`, `OwnerRevenueReportPage.tsx`, `pages/HomePage.tsx`(+test), `routes/AppRoutes.tsx`, `components/layout/AdminLayout.tsx`, `RouteGuard.test`, `AuthFlowIntegration.test`.

---
OWNER: Executive Summary = PASS · Laporan Pendapatan = PASS · Perbedaan fungsi = PASS
ADMIN CMS: Brand = PASS · Navbar = PASS · Hero = PASS · Media = PASS · Public sync = PASS · Authorization = PASS
FINAL: **READY**

## Follow-up — Brand & Navbar full landing integration
Sebelumnya hanya hero yang otomatis; kini brand/navbar ikut.
- `CmsProvider` (`features/cms/CmsContext.tsx`) memuat `GET /api/v1/cms/public` sekali untuk layout publik.
- `PublicLayout` membungkus dengan provider + favicon (link icon) & `document.title` dari `brand_favicon`/`brand_name` + footer `brand_name`.
- `Navbar` menampilkan `brand_logo` (img, fallback ikon HardHat), `brand_name`, dan menu dari `navbar` JSON; fallback default bila kosong. Tahan di luar provider (layar user/admin) → default.
- `HomePage` kini via `useCms()` (hero title/subtitle/CTA/hero image tetap).
- Tests baru `PublicLayout.test` (logo/nama/menu/footer; fallback) + `HomePage.test` memakai provider.
- Regression: frontend **153 passed (40 files)**, tsc/build/lint PASS.

Jadi: Admin ubah logo/favicon/nama/menu → tersimpan DB → `GET /cms/public` → **Navbar/Landing/Footer ikut berubah** (perlu page-load untuk tab landing yang sudah terbuka; tidak live-push).