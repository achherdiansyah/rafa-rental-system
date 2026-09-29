# UI/UX Redesign Report — RAFA Rental

Tujuan: re-tema frontend ke visual corporate modern minimal (heavy-equipment rental platform). Backend/database/API/business logic TIDAK diubah. Tidak ada mock/fake data; semua memakai service/API existing.

## Visual direction
Minimalist, clean, corporate, premium: white/off-white surfaces, navy typography (`slate-900`), strong-blue primary, gold accent, thin borders, soft shadow, moderate radius, generous whitespace. Tanpa neon/glassmorphism/gradient-berat.

## Color system (tokens)
Tailwind v4 `@theme` di `src/index.css`:
- Primary blue existing dipertegas (corporate).
- **Accent gold** baru `--color-accent-*` (highlight/CTA emphasis).
- Radius tokens (`--radius-sm/md/lg/xl`) + soft shadows (`--shadow-card/hover/btn`).
- Utility `card-surface` (white + 1px slate-200 + radius-lg + soft shadow) + `.page-title`/`.page-subtitle`.

## Typography
Inter (existing). H1 besar bold navy; H2 semibold; body slate-600 readable; caption slate-400. Anti-italic/uppercase berlebihan.

## Component system (baru, reusable)
- `PageHeader` — pola [Title] + subtitle + CTA kanan; diadopsi di Master Data, Lokasi Proyek, Rekening Bank.
- `StatusBadge` — map status → tone+label tunggal untuk Equipment/Booking/Rental/Timesheet/Invoice/Payment/Refund (presentasi saja; enum business tidak diubah).
- `StatusTimeline` — timeline lifecycle ringan.
- `KpiCard` — KPI surface konsisten.
- `FileUpload` — gambar: preview lokal + persisted URL, validasi JPG/PNG/WebP ≤5MB, replace/remove, loading/error.
- Base `Button`/`Card`/`Badge`/`Table`/`Modal` existing dipertahankan (sudah sesuai token).

## Public landing (redesign)
- Hero **split layout**: kiri label brand (CMS `brand_name`) + headline/left (`hero_title`, `hero_subtitle`) + CTA (`hero_cta_text/link`) + "Lihat Katalog"; kanan hero image dari CMS (`hero_image`, fallback netral tanpa gambar palsu).
- Navbar publik: logo/nama dari CMS, menu default Beranda/Equipment/Tentang Kami/Kontak (anchor section) atau menu JSON CMS bila diatur; kanan **Masuk / Daftar**.
- Section: Kategori Alat Berat (dari API nyata `/equipment/types`, fallback daftar kategori generik — bukan fake data), Tentang Kami (+ value props), CTA, Kontak, Footer (brand CMS).
- Section anchor `#equipment/#about/#contact` sesuai menu.

## User / Admin / Owner
Seluruh portal mewarisi token (button/card/badge/table) global — konsistensi visual otomatis. Halaman kunci memakai `PageHeader` baru. Status/business flow, hook, service, dan API TIDAK berubah (timesheet user tetap read/confirm/sign; CMS admin tetap fungsi).

## CMS redesign
Berfungsi (migrasi sebelumnya) + kini shared `FileUpload` untuk logo/favicon/hero, `CmsProvider` untuk sync landing; `brand_name/navbar/hero/about/services/cta/footer` via konten teks; autorisasi admin-only & public read tetap.

## Responsive / Accessibility / Performance
- Grid/table/sidebar existing responsif (3/2/1 katalog, hamburger mobile) — dipertahankan.
- Focus ring visible global; label form; aria-label pada ikon-aksi; kontras slate/primary memenuhi.
- Tanpa dependency baru (hanya file UI sendiri); lazy routes existing; tidak ada request tambahan selain data yang memang dibutuhkan.

## Regression
- Frontend `vitest`: **153 passed (40 files)** — termasuk HomePage (CMS sync + fallback), PublicLayout (brand/navbar/menu/footer), cms service, layout, auth, booking, timesheet, reporting.
- `tsc --noEmit`, `npm run build`, `oxlint` → PASS.
- Backend `php artisan test` (tidak diubah di branch ini): **473 passed** (terakhir diverifikasi).

## Files changed (frontend only)
- `src/index.css` (tokens: accent, radius, shadow, card-surface, page-title/subtitle)
- `src/components/ui/{PageHeader,StatusBadge,StatusTimeline,KpiCard,FileUpload}.tsx` (baru)
- `src/components/layout/{Navbar,PublicLayout}.tsx` (+`PublicLayout.test`)
- `src/pages/HomePage.tsx` (+`HomePage.test`) — landing redesign + categories
- `src/features/project/pages/UserProjectLocationsPage.tsx`, `src/features/bank/pages/AdminBankAccountsPage.tsx`, `src/features/equipment/pages/AdminEquipmentMasterPage.tsx` (PageHeader adoption)

## Catatan scope lanjutan (bukan blokir)
Pas pertama ini menata fondasi token + komponen + landing + header kunci. Detail layer berikutnya (filter sidebar catalog, restyle menu CTA, timeline adoption di rental/admin, table responsive card) siap memakai komponen `StatusBadge/StatusTimeline/KpiCard/FileUpload` di pass berikutnya tanpa perubahan API/DB.

---
FINAL: **READY** (fondasi & acceptance inti PASS; layer visual lanjutan non-blocking)