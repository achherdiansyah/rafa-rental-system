# cPanel Production Readiness Checklist — RAFA Rental System

Status validasi akhir sebelum rilis produksi di shared hosting cPanel.
**Dokumen persiapan — deployment dijalankan manual oleh operator.**

## Stack (tanpa Docker/Node runtime/Redis di produksi)

- [x] **PHP 8.2** — `composer.json`: `"php": "^8.2"`; runtime uji 8.2.12 ✓
- [x] **MySQL 8.x** — engine/tipe data kompatibel (DECIMAL(15,2), JSON/ENUM via casts), seluruh suite berjalan di MySQL 8.4 ✓
- [x] **React build** — `npm run build` (tsc -b + vite) memproduksi `dist/`; 121 tes hijau ✓
- [x] **Node diperlukan hanya saat build** (produksi tidak butuh Node server)
- [x] **Tanpa Redis/Horizon/MinIO/Docker** — cache/session file, queue database, storage lokal

## Laravel Public Document Root

- [ ] Docroot cPanel → `backend/public` (subdirektori Laravel). Contoh `public_html/rafa` berisi:
  ```
  backend/                 # seluruh app (di luar docroot)
      public/              # docroot → backend/public
  frontend/dist/           # (opsional) hasil build React utk origin terpisah
  ```
- [ ] Pastikan `backend/public/index.php` & `.htaccess` ada; hanya `public/` yang terekspos web.

## Environment Production

- [ ] Salin `backend/.env.production.example` → `backend/.env`
- [ ] `php artisan key:generate` (isi `APP_KEY`)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL` = https domain; `FRONTEND_URL`/`CORS_ALLOWED_ORIGINS` = origin depan
- [ ] `DB_*` isi kredensial MySQL cPanel
- [ ] `SANCTUM_EXPIRATION=10080`, `CACHE_STORE=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=database`
- [ ] `WHATSAPP_*` kosong bila provider belum dipasang (delivery `SKIPPED` secara jujur)

## Storage Link & Private Storage

- [ ] `php artisan storage:link` (buat symlink `public/storage` → `storage/app/public`)
- [ ] File privat (bukti pembayaran/refund/ttd) tersimpan di `storage/app/` (di luar docroot), `url:null`
- [ ] Izin dir: `storage/` & `bootstrap/cache/` writable

## Database Migration

- [ ] `php artisan migrate --force`
- [ ] `php artisan db:backup` pertama (simpan dump awal)

## Scheduler & Queue (agar andal di shared hosting)

- [ ] Cron (Linux cPanel → Cron Jobs):
  ```
  * * * * * php /path/backend/artisan schedule:run >> /path/backend/storage/logs/scheduler.log 2>&1
  ```
- [ ] Opsi queue (bila WA async digunakan):
  ```
  * * * * * php /path/backend/artisan queue:work database --once --tries=1 --max-time=60 >> /path/backend/storage/logs/queue.log 2>&1
  ```
  (bukan daemon; `--once` aman utk shared hosting; job WhatsApp `$tries=1` anti-duplikasi)
- [ ] Jadwal aktif: `bookings:expire`, `invoices:expire` (tiap menit), `outstanding:remind` (07:30), `db:backup` (01:00) — semua `withoutOverlapping`

## Cache / Config / Route Optimization

- [ ] `php artisan event:cache`
- [ ] `php artisan config:cache` **setelah** `.env` final (config menyimpan nilai env saat itu)
- [ ] `php artisan route:cache` (jangan jika ada closure route; repo bersih dari closure — aman)
- [ ] `php artisan view:cache`
- [ ] Peringatan: ubah `.env` setelah caching → jalankan ulang `config:cache`.

## CORS & HTTPS / Proxy

- [ ] `config/cors.php` dibaca dari `CORS_ALLOWED_ORIGINS` (array env, koma)
- [ ] `bootstrap/app.php` — `trustProxies(at: env('TRUSTED_PROXIES', '*'))` → URL https benar di belakang proxy/AutoSSL
- [ ] Pastikan Access-Control-Allow-Origin mencakup origin frontend (test via `OPTIONS` preflight)

## Pasca-Deploy (verifikasi)

- [ ] `php artisan migrate:status` → up to date
- [ ] `/api/v1/health` → 200 (tanpa stack trace; JSON `{"success":true,…}`)
- [ ] Login ADMIN end-to-end; buat data kecil utk smoke (booking→rental→timesheet→invoice)
- [ ] Coba `storage:audit-orphans` → tidak ada laporan mencurigakan
- [ ] `php artisan about` — environment production, debug off

## Artefak Kesiapan (dari repo)

- `backend/.env.production.example`
- `backend/config/cors.php` (env-driven)
- `bootstrap/app.php` trustProxies
- Perintah ops: `db:backup`, `storage:audit-orphans`
- Test: full suite backend **451 passed**, frontend **121 passed**, build produksi sukses.