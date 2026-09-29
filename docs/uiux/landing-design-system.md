# Landing Page Design System — RAFA Rental

Scope: **public landing page only**. Backend / database / API / auth / availability / booking / rental / timesheet / billing / invoice / payment / refund / outstanding — TIDAK diubah. Dokumen ini = audit + sistem desain untuk landing.

## 1. Audit — struktur landing existing

| Bagian | File / komponen | Catatan |
|---|---|---|
| Shell publik | `src/components/layout/PublicLayout.tsx` | `CmsProvider` + `FaviconSync` (favicon + document.title dari CMS) + `Navbar` + `Outlet` + footer brand |
| Navbar | `src/components/layout/Navbar.tsx` | Logo/nama dari CMS (fallback), menu default Beranda/Equipment/Tentang Kami/Kontak (anchor `/#equipment|#about|#contact`) atau menu JSON CMS; kanan **Masuk / Daftar** (guest) / profil+portal (login) |
| Halaman utama | `src/pages/HomePage.tsx` | Hero split, kategori armada (API nyata `/equipment/types`), Tentang + value props, CTA, Kontak, footer |
| Halaman auth | `/login`, `/register`, `/forgot-password`, `/reset-password` (`AuthLayout`) | reuse design token yang sama |
| Halaman lain publik | `/unauthorized`, `/forbidden`, 404 | token yang sama |

## 2. Audit — komponen tersedia & reusable

`src/components/ui/`: `Button`, `Card`, `Badge`, `StatusBadge`, `Breadcrumb`, `Dropdown`, `Tooltip`, `Tabs`, `Modal`, `ConfirmDialog`, `Skeleton`, `PageHeader`, `KpiCard`, `StatusTimeline`, `FileUpload`.
`src/components/feedback/`: `EmptyState`, `ErrorState`, `LoadingState`, `Alert`, `NetworkErrorBanner`, `Toast`.
`src/components/data-display/`: `Table` (+ `Pagination`).

Untuk landing dipakai: `Button`, `Card`, `Badge` (`StatusBadge`), placeholder netral (bukan gambar palsu). Component baru lain tersedia untuk portal, tidak perlu di-demo di landing.

## 3. Audit — data CMS yang dikonsumsi frontend (existing)

Endpoint `GET /api/v1/cms/public` (existing, tidak diubah). Kunci yang dipakai landing:

| Key | Konsumen |
|---|---|
| `brand_name` | Navbar nama + `document.title` (FaviconSync) + hero label + footer |
| `brand_logo` | Navbar logo img (fallback ikon HardHat) |
| `brand_favicon` | `FaviconSync` → `<link rel="icon">` |
| `hero_title` | headline H1 |
| `hero_subtitle` | supporting text |
| `hero_cta_text` | CTA utama |
| `hero_cta_link` | CTA tujuan |
| `hero_image` | gambar kanan hero (fallback: placeholder netral) |
| `navbar` | menu JSON navbar (fallback menu default) |
| `about`, `cta_section`, `footer` | section Tentang / CTA / footer |
| `services` | tersedia di CMS, belum dirender landing (cadangan) |

Semua key punya fallback default → landing selalu utuh tanpa konfigurasi.

## 4. Audit — route / navbar existing

- `'/'` → `PublicLayout` → `HomePage` (public, tanpa auth).
- Navbar guest: menu + **Masuk** (`/login`) **Daftar** (`/register`); login: search/portal sesuai role (existing).
- Anchor section: `/#equipment`, `/#about`, `/#contact`.

## 5. Design system

### 5.1 Prinsip
Minimalist · modern · clean · corporate · premium · professional · lightweight · human-designed.
Larangan: glassmorphism, neon, gradient berlebihan, animasi berlebihan, decorative blob, radius/shadow berlebih, ikon dekoratif tanpa fungsi, copywriting generik AI, layout SaaS generik.

### 5.2 Color
- Background: `#ffffff`, off-white `#fafbfc` / `slate-50#f8fafc`.
- Text: navy `slate-900 #0f172a` (heading), body `slate-600 #475569`, muted `slate-400 #94a3b8`.
- Primary (CTA): `primary-600 #026bc7` (bg + white text); hover `primary-700`.
- Accent: gold `accent-400 #fbbf24` / `accent-500 #f59e0b` — highlight/emphasis terbatas.
- Border: `slate-200 #e2e8f0` (thin 1px).
- Status: hijau=success (`emerald-500`), amber=warning (`amber-500`), merah=danger (`rose-500`), biru=info (`primary-500`).
- Shadow: `--shadow-card` (1px/soft) & `--shadow-card-hover`; `--shadow-btn` untuk tombol.

### 5.3 Spacing (skala 4/8px)
`4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64 · 80 · 96`
Section spacing landing: `64–96` vertical; card padding `20–32`; gap antar blok `24–40`.

### 5.4 Container
Desktop max-width `1200–1280px`; gutter desktop `32–48`, tablet `24–32`, mobile `16–20`. Landing saat ini `max-w-7xl` (1280) + `px-4 sm:px-6 lg:px-8` (16/24/32) ✓ sesuai.

### 5.5 Typography (Inter)
- Hero H1: `56–72px` (desktop) / `44–56` tablet / `36–44` mobile — `font-extrabold tracking-tight text-slate-900`. (Tailwind: `text-4xl sm:text-5xl` = 36/48 — naikkan ke `text-5xl lg:text-6xl` saat diperlukan.)
- Section heading: `32–40px` bold navy.
- Body: `16–18px` slate-600, `leading-relaxed`.
- Helper/caption: `13–14px` slate-400/500.
- Tanpa decorative/italic/uppercase berlebihan.

### 5.6 Komponen landing (reuse)
- **Button primary**: bg `primary-600`, white text, radius `--radius-md` (10px), icon optional, `--shadow-btn`, hover `primary-700`, transition 150–200ms. Sesuai `Button.tsx` variant `primary`.
- **Button secondary**: white bg, `slate-200` border, dark text.
- **Card**: `card-surface` (white, 1px slate-200, radius-lg, soft shadow), padding 20–28 (`p-5/p-6`).
- **Badge/StatusBadge**: status semantics (presentasi saja).
- **Section header pattern**: `PageHeader` (title + subtitle, CTA kanan bila perlu).
- **Empty/Error/Loading**: `Skeleton`, `EmptyState`, `ErrorState` (tidak muncul saat loading; tanpa false state).

### 5.7 Landing sections (target layout)
1. **Navbar** — logo kiri (CMS), menu kiri/tengah, aksi kanan (Masuk/Daftar).
2. **Hero split** — kiri: label brand, H1 besar, supporting, CTA primary + link sekunder (Lihat Katalog); kanan: hero image (CMS) sebagai focal point + subtle shape; whitespace luas.
3. **Kategori** — grid 2/3 kolom dari API nyata (bukan mock; count hanya bila data ada).
4. **Tentang/Value** — heading + copy yang manusiawi (dimulai dari `cms.about`) + 3 value props.
5. **CTA** — satu permukaan `card-surface`, satu CTA.
6. **Kontak & Footer** — ringkas, brand dari CMS.

### 5.8 Responsive
Desktop 3 kolom, tablet 2 (drawer/collapsed nav), mobile 1 (+ hamburger menu guest jika menu bertambah). Tidak ada horizontal overflow; image `aspect-[4/3] object-cover`.

### 5.9 Aksesibilitas
Semantik (`section/h1/h2/footer`), `aria-label` ikon-aksi, focus ring visible (global), label form, kontras navy/blue.

## 6. Reuse decision
- Jangan rewrite frontend: `PublicLayout`, `Navbar`, `HomePage`, `CmsProvider`, `Button/Card/Badge` sudah sesuai token; tinggal penyesuaian minor spacing/type (mis. hero ukuran) memakai tokens existing.
- Tidak ada endpoint/DB/backend change.

## 7. Acceptance
Token & utilitas (`card-surface`, `page-title/subtitle`, accent, radius/shadow) sudah aktif di `src/index.css`; komponen baru reusable; CTA & teks dari CMS; tanpa mock; build PASS.