# Public Landing Page Redesign Report — RAFA Rental

Final: **READY**.

## Design system
Minimalist · modern · clean · corporate · premium · professional · human-designed.
- Color: white/off-white background, navy text (`slate-900`), strong-blue primary CTA, gold accent (CTA band & `Button accent`), thin `slate-200` borders, soft shadow, moderate radius (token `--radius-*`).
- Typography: Inter; hero H1 `4xl→5xl→6xl`, section h2, body 16–18 slate-600, caption 13–14 slate-400.
- Spacing skala 4/8; whitespace luas; container `max-w-7xl`, gutter 16/24/32.
- Anti-slop: tanpa gradient tak perlu (hero flat `bg-slate-100`), tanpa dekorasi acak, radius konsisten, ikon fungsional, tanpa animasi berlebih, copy realistis.

## Sections
Navbar (sticky, 80px, putih, border tipis, menu + Masuk/Daftar) · Hero (45/55 split, eyebrow/H1/subtitle/CTA + gambar dominan) · Statistics (light strip 2×2) · Kategori (API `/equipment/types` atau daftar fallback) · Armada Unggulan (API `/equipment/models` 6 item atau fallback dummy realistis) · About (split, CMS `about`) · Benefits (grid 4) · CTA band (navy, gold accent) · Contact (minimal, tanpa info palsu) · Footer (logo+brand+nav+copyright).

## Navigation
Menu Beranda/Equipment/Tentang Kami/Kontak → scroll ke `#home|#equipment|#about|#contact`; klik juga **meng-set URL hash** (refresh-safe + active state); smooth scroll hormati `prefers-reduced-motion`; sticky navbar tidak menutupi target (`scroll-mt-24`). Masuk→`/login`, Daftar→`/register`; CTA/Catalog→`/app/equipment` (route existing, logic catalog tidak diubah).

## CMS integration
`GET /api/v1/cms/public` (contract existing) → `CmsProvider` (fetch sekali, StrictMode-guarded). Field dipakai: `brand_name/brand_logo/brand_favicon/hero_title/hero_subtitle/hero_cta_text/hero_cta_link/hero_image/navbar/about/cta_section/footer`. Hero + navbar + footer mengikuti CMS; perubahan Admin → tampil di landing.

## Fallback
Satu sumber terisolasi `landingFallbackData.tsx` (copy realistis bisnis rental, tanpa lorem/placeholder kosong). Resolusi per-field `cms ?? fallback` — **CMS selalu menang**; fallback hanya mengisi field kosong. Dummy hero image = aset lokal `hero-equipment.svg`.

## Responsive
Diuji Playwright pada 1440/1280/1024/768/390/360 — **tanpa horizontal overflow**; hero 2→1 kolom, stats 2×2, equipment 1/2/3–4 kolom, hamburger mobile dengan panel + Escape/klik-luar menutup.

## Accessibility
H1 tunggal; hierarchy h2 konsisten; `nav`/`section`/`footer` semantik + `aria-label`; focus visible global; gambar `alt`; tombol/link dapat diaktivasi keyboard; kontras navy/blue/white mencukupi.

## Performance
Tanpa dependency baru; `CmsProvider` + `HomePage` fetch **satu kali** per sumber (ref-guard StrictMode, no duplicate request); hero `loading="eager" decoding="async"`; non-hero `loading="lazy"`; lazy routes portal; tanpa animasi berat.

## Regression (browser nyata — `e2e/landing.pw.ts`)
1) Load/no-console/no-broken-image/no-duplicate-request PASS
2) Navbar menu + Masuk/Daftar href PASS
3) Hash navigation (URL sync + offset) PASS
4) Hero+CTA+category render + hierarchy PASS
5) Mobile hamburger buka/tutup + no overflow PASS
6) Responsive widths no overflow PASS
7) Keyboard activation PASS
Suite vitest **160 passed (41 files)**; `tsc`, `npm run build`, `oxlint` PASS; backend `php artisan test` **473 passed (2636)**.

Bug yang ditemukan & fixed saat uji: menu hash dulu hanya scroll tanpa sync URL → kini `location.hash` ikut di-set (root-cause fix).

## Files changed (landing scope, frontend-only)
- `src/components/layout/{Navbar,PublicLayout}.tsx` (+`Navbar.test`, `PublicLayout.test`)
- `src/pages/HomePage.tsx` (+`HomePage.test`) — hero/stats/category/featured/about/benefits/CTA/contact/footer
- `src/hooks/useHashScroll.ts` (baru)
- `src/features/cms/{CmsContext.tsx,landingFallbackData.tsx}` (baru)
- `src/features/cms/services/cmsService.ts` (+test) — contract dipakai ulang
- `src/components/ui/{PageHeader,StatusBadge,StatusTimeline,KpiCard,FileUpload}.tsx` + `Button` variant `accent`
- `src/index.css` (tokens: accent/radius/shadow/card-surface/smooth-scroll/overflow guard)
- `public/hero-equipment.svg`
- `e2e/landing.pw.ts` (baru), `playwright.config.ts`
- `docs/uiux/landing-design-system.md`, `docs/uiux/uiux-redesign-report.md`

Backend/database/API contract/business logic: **TIDAK diubah** oleh pekerjaan landing ini (commit landing murni frontend).