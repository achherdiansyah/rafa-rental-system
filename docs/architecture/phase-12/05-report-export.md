# 05 — Report Export (Phase 12E)

Ekspor laporan baris yang sudah tersedia menjadi CSV (Excel-friendly).

## Endpoint

`GET /api/v1/reports/export/{type}?from&to&status&customer_id&project_id&model_id&unit_id&rental_id&source&sort_by&sort_dir`

- `type`: `bookings`, `timesheets`, `rentals`, `activity`, `invoices`,
  `payments`, `partials`, `outstanding`, `overpayments`, `refunds`,
  `equipment` (ADMIN/OWNER).
- **Export mengikuti filter aktif** — query yang sama dengan API list
  (reuse service listing + trait filter/sort/scope).
- **Hanya data yang berhak** — scope USER diterapkan (milik sendiri);
  `equipment` → 403 utk USER.
- **Streaming/chunking** — ditulis baris per baris (`fputcsv`) ke stream,
  iterasi paginator (halaman 1..N, `per_page` 500) sehingga dataset besar
  tidak muat seluruhnya di memori; paginator diberi param `page` eksplisit
  (trait `FiltersAndPagination` kini menerima `filters['page']`).
- **Tanpa data sensitif** — kolom hanya whitelist report (tanpa nomor bank
  detail, tanpa bukti, tanpa konten privat).
- **Nama file konsisten**: `rafa-report-{type}-{from}-{to}.csv`
  (`{from}/{to}` = `open` bila filter periode kosong).
- **Tidak mengubah data sumber** — read-only; `X-Content-Type-Options: nosniff`,
  UTF-8 BOM agak Excel.

## Test Result — `ExportReportTest` (4 kasus, 18 assertions)

- CSV bookings + filter status/periode: header + baris hanya CONFIRMED,
  filename `rafa-report-bookings-2026-09-01-to-2026-09-30.csv`, BOM, content-type
  csv.
- User scope: hanya row miliknya; customer lain tidak bocor.
- Equipment export → 403 utk USER; payments export utk admin berbasis row.
- Chunking: 12 booking diekspor utuh (bantuan `per_page=5` melewati beberapa
  halaman); dataset tidak berubah (count & status tetap).

**Backend total 425 passed (2263 assertions)**; Pint clean. Frontend tak tersentuh.

## Files

- `app/Http/Controllers/Api/V1/ReportingController.php` (`export`, `listingResolver`, `exportFilename`)
- `app/Services/Reports/Concerns/FiltersAndPagination.php` (+`page` param)
- `routes/api.php` (`GET /reports/export/{type}`)
- `tests/Feature/Reports/ExportReportTest.php`