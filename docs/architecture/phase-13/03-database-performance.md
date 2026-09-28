# 03 — Database & Performance Optimization (Phase 13C)

Tuning akses DB dan konfirmasi performa tanpa mengubah business result.

## Audit

- **N+1**: sudah dicegah (eager loading, agregasi SQL, subquery). Dilindungi guard
  kuantitas query di test (rental list < 12 query).
- **Query lambat**: jalur utama (status listing, filter report, cron deadline,
  aggregasi) difilter oleh kolom berstatus/ber-tanggal.
- **Locking finansial & availability**: `VerifyPaymentAction` dan pencadangan unit
  memakai `lockForUpdate` (pessimistic) — sudah; tidak berubah.
- **Frontend**: seluruh rute lazy-loaded; bundle utama 286 kB (gzip 90 kB) tanpa
  dependency berat tambahan — tidak ada needs untuk manualChunks baru.

## Perubahan

Migration `2026_09_25_000014_add_performance_indexes_table.php` — index murni
(access-path), business behavior tetap:

| Tabel | Index |
|---|---|
| `timesheets` | (`status`, `report_date`); unique (`rental_detail_id`, `report_date`) sudah ada |
| `invoices` | (`status`, `created_at`); (`due_at`) |
| `payments` | (`status`, `payment_date`) |
| `refunds` | (`status`, `created_at`); (`source`) |
| `bookings` | (`status`, `created_at`) |
| `rentals` | (`status`, `created_at`) |

Tidak ada penambahan kompleksitas query baru; aggregasi/lazy loading/pagination
seperti sebelumnya.

## Test — `PerformanceTest` (3 kasus, 16 assertions)

- Index jalur panas hadir (9 check termasuk unique timesheet).
- List rental (admin, 5 rental w/ detail+unit+model) eager-loaded → **< 12
  query** (bukan N+1).
- Jalur baca (dashboard/operational/financial) **tidak memutasi** state bisnis.

## Hasil Eksekusi

| Command | Hasil |
|---|---|
| `php artisan test` | **442 passed (2425 assertions)** |
| `./vendor/bin/pint --test` | passed |
| `npm run build` | sukses |
| Profiling/query inspection | via `DB::listen` guard di test |

## Files

- `database/migrations/2026_09_25_000014_add_performance_indexes_table.php`
- `tests/Feature/Performance/PerformanceTest.php`